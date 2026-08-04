<?php
/**
 * RT-315 encrypted private-media storage tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Infrastructure\Media;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReturnTag\TagCore\Application\FinderReport\PrivateMediaObject;
use ReturnTag\TagCore\Application\Persistence\Value\PrivateMediaReferenceCiphertext;
use ReturnTag\TagCore\Domain\FinderReport\PrivateMediaObjectKind;
use ReturnTag\TagCore\Infrastructure\Media\PrivateMediaSecrets;
use ReturnTag\TagCore\Infrastructure\Media\SodiumFilesystemPrivateMediaStorage;
use RuntimeException;

/**
 * Verifies authenticated storage, opaque references, purpose binding, and cleanup.
 */
final class SodiumFilesystemPrivateMediaStorageTest extends TestCase {
	/**
	 * Unique private test root.
	 *
	 * @var string
	 */
	private string $root;

	/** Create a unique private test root. */
	protected function setUp(): void {
		parent::setUp();
		$this->root = rtrim( str_replace( '\\', '/', sys_get_temp_dir() ), '/' ) . '/returntag-rt315-' . bin2hex( random_bytes( 8 ) );
	}

	/** Remove only the unique test root. */
	protected function tearDown(): void {
		$this->remove_test_root();
		parent::tearDown();
	}

	/** Stored bytes and references are encrypted and recover only in the right purpose. */
	public function test_round_trip_uses_encrypted_non_public_storage(): void {
		$storage   = $this->storage();
		$plaintext = 'synthetic-private-image-bytes';
		$object    = $storage->put( PrivateMediaObjectKind::SOURCE, $plaintext );
		$files     = $this->object_files();

		self::assertCount( 1, $files );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local synthetic encrypted fixture.
		self::assertStringNotContainsString( $plaintext, (string) file_get_contents( $files[0] ) );
		self::assertStringNotContainsString( $plaintext, $object->reference_ciphertext->value );
		self::assertSame( $plaintext, $storage->read( PrivateMediaObjectKind::SOURCE, $object ) );

		$this->expectException( RuntimeException::class );
		$storage->read( PrivateMediaObjectKind::EMAIL, $object );
	}

	/** A modified encrypted reference fails authentication. */
	public function test_tampered_reference_fails_closed(): void {
		$storage  = $this->storage();
		$object   = $storage->put( PrivateMediaObjectKind::REVIEW, 'synthetic-review-bytes' );
		$tampered = new PrivateMediaObject(
			PrivateMediaReferenceCiphertext::from_encrypted_bytes( $object->reference_ciphertext->value . 'A' ),
			$object->encryption_key_id,
			$object->sha256,
			$object->byte_count
		);

		$this->expectException( RuntimeException::class );
		$storage->read( PrivateMediaObjectKind::REVIEW, $tampered );
	}

	/** Modified encrypted object bytes fail authenticated decryption. */
	public function test_tampered_object_fails_closed(): void {
		$storage = $this->storage();
		$object  = $storage->put( PrivateMediaObjectKind::SOURCE, 'synthetic-source-bytes' );
		$file    = $this->object_files()[0];
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local synthetic encrypted fixture.
		$bytes = file_get_contents( $file );
		self::assertIsString( $bytes );
		$tampered = substr( $bytes, 0, -1 ) . chr( ord( substr( $bytes, -1 ) ) ^ 1 );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Mutates only a local synthetic encrypted fixture.
		file_put_contents( $file, $tampered );

		$this->expectException( RuntimeException::class );
		$storage->read( PrivateMediaObjectKind::SOURCE, $object );
	}

	/** Delete is idempotent and removes decryptable storage bytes. */
	public function test_delete_is_idempotent(): void {
		$storage = $this->storage();
		$object  = $storage->put( PrivateMediaObjectKind::EMAIL, 'synthetic-email-bytes' );

		$storage->delete( PrivateMediaObjectKind::EMAIL, $object );
		$storage->delete( PrivateMediaObjectKind::EMAIL, $object );
		self::assertSame( array(), $this->object_files() );

		$this->expectException( RuntimeException::class );
		$storage->read( PrivateMediaObjectKind::EMAIL, $object );
	}

	/** Public or web-root-relative storage configuration is rejected. */
	public function test_rejects_storage_inside_a_public_root(): void {
		$this->expectException( RuntimeException::class );
		new SodiumFilesystemPrivateMediaStorage(
			str_replace( '\\', '/', (string) getcwd() ) . '/private-media',
			$this->secrets(),
			array( (string) getcwd() )
		);
	}

	/** Object and reference encryption must never reuse one key. */
	public function test_rejects_reused_keys(): void {
		$this->expectException( RuntimeException::class );
		PrivateMediaSecrets::from_keys( str_repeat( 'x', 32 ), str_repeat( 'x', 32 ) );
	}

	/**
	 * Build the test adapter.
	 */
	private function storage(): SodiumFilesystemPrivateMediaStorage {
		return new SodiumFilesystemPrivateMediaStorage( $this->root, $this->secrets(), array( (string) getcwd() ) );
	}

	/**
	 * Build deterministic test-only key material.
	 */
	private function secrets(): PrivateMediaSecrets {
		return PrivateMediaSecrets::from_keys( str_repeat( 'o', 32 ), str_repeat( 'r', 32 ) );
	}

	/**
	 * Return private object files under the unique test root.
	 *
	 * @return list<string>
	 */
	private function object_files(): array {
		if ( ! is_dir( $this->root ) ) {
			return array();
		}

		$files = array();
		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $this->root, FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $items as $item ) {
			if ( $item->isFile() ) {
				$files[] = str_replace( '\\', '/', $item->getPathname() );
			}
		}

		return $files;
	}

	/** Remove files and directories under the unique test root. */
	private function remove_test_root(): void {
		$temporary = rtrim( str_replace( '\\', '/', sys_get_temp_dir() ), '/' ) . '/returntag-rt315-';

		if ( ! str_starts_with( $this->root, $temporary ) || ! is_dir( $this->root ) ) {
			return;
		}

		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $this->root, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			if ( $item->isDir() ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removes only the validated unique test root.
				rmdir( $item->getPathname() );
			} else {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes only synthetic test objects.
				unlink( $item->getPathname() );
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removes only the validated unique test root.
		rmdir( $this->root );
	}
}
