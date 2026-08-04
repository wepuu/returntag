<?php
/**
 * RT-315 GD Finder evidence tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Infrastructure\Media;

use GdImage;
use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\FinderReport\FinderEvidenceProcessingException;
use ReturnTag\TagCore\Application\FinderReport\FinderEvidenceSource;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceMime;
use ReturnTag\TagCore\Infrastructure\Media\GdFinderEvidenceImageProcessor;

/**
 * Verifies signature checks, decode bounds, metadata removal, and derivatives.
 */
final class GdFinderEvidenceImageProcessorTest extends TestCase {
	/** A valid image becomes two bounded metadata-free local JPEG derivatives. */
	public function test_processes_and_strips_one_valid_image(): void {
		$source    = $this->image_blob( 'jpeg' );
		$processed = ( new GdFinderEvidenceImageProcessor() )->process( new FinderEvidenceSource( $source ) );

		self::assertSame( FinderEvidenceMime::JPEG, $processed->source_mime );
		self::assertSame( 2400, $processed->source_width );
		self::assertSame( 1200, $processed->source_height );
		self::assertSame( 1600, $processed->review->width );
		self::assertSame( 800, $processed->review->height );
		self::assertLessThanOrEqual( 800, max( $processed->email->width, $processed->email->height ) );
		self::assertLessThanOrEqual( 204800, $processed->email->byte_count() );
		self::assertSame( "\xFF\xD8", substr( $processed->review->bytes, 0, 2 ) );
		self::assertStringNotContainsString( 'synthetic-private-metadata', $processed->review->bytes );
	}

	/** PNG and WebP signatures are accepted from server-detected bytes. */
	public function test_accepts_each_approved_source_mime(): void {
		$processor = new GdFinderEvidenceImageProcessor();

		self::assertSame( FinderEvidenceMime::PNG, $processor->process( new FinderEvidenceSource( $this->image_blob( 'png' ) ) )->source_mime );
		self::assertSame( FinderEvidenceMime::WEBP, $processor->process( new FinderEvidenceSource( $this->image_blob( 'webp' ) ) )->source_mime );
	}

	/** JPEG EXIF orientation is applied before metadata-free derivatives are encoded. */
	public function test_applies_and_removes_jpeg_orientation(): void {
		$source    = $this->with_orientation( $this->image_blob( 'jpeg' ), 6 );
		$processed = ( new GdFinderEvidenceImageProcessor() )->process( new FinderEvidenceSource( $source ) );

		self::assertSame( 800, $processed->review->width );
		self::assertSame( 1600, $processed->review->height );
		self::assertStringNotContainsString( "Exif\0\0", $processed->review->bytes );
	}

	/** Appended bytes are rejected as polyglot-like input. */
	public function test_rejects_appended_content(): void {
		$this->expectException( FinderEvidenceProcessingException::class );
		( new GdFinderEvidenceImageProcessor() )->process(
			new FinderEvidenceSource( $this->image_blob( 'jpeg' ) . '<script>unsafe</script>' )
		);
	}

	/** Non-image bytes fail with a fixed processing exception. */
	public function test_rejects_unsupported_bytes(): void {
		$this->expectException( FinderEvidenceProcessingException::class );
		( new GdFinderEvidenceImageProcessor() )->process( new FinderEvidenceSource( '%PDF-1.7 synthetic' ) );
	}

	/**
	 * Build a one-frame synthetic source image.
	 *
	 * @param string $format GD output format.
	 */
	private function image_blob( string $format ): string {
		$image = imagecreatetruecolor( 2400, 1200 );
		self::assertInstanceOf( GdImage::class, $image );
		$red = imagecolorallocate( $image, 239, 35, 60 );
		self::assertIsInt( $red );
		imagefill( $image, 0, 0, $red );

		ob_start();
		$success = match ( $format ) {
			'jpeg' => imagejpeg( $image, null, 90 ),
			'png' => imagepng( $image ),
			'webp' => imagewebp( $image, null, 85 ),
			default => false,
		};
		$bytes = ob_get_clean();
		imagedestroy( $image );
		self::assertTrue( $success );

		if ( 'jpeg' === $format ) {
			$payload = 'synthetic-private-metadata';
			$comment = "\xFF\xFE" . pack( 'n', strlen( $payload ) + 2 ) . $payload;
			$bytes   = substr( $bytes, 0, 2 ) . $comment . substr( $bytes, 2 );
		}

		return $bytes;
	}

	/**
	 * Inject one bounded little-endian EXIF orientation fixture.
	 *
	 * @param string $jpeg Source JPEG.
	 * @param int    $orientation Orientation value.
	 */
	private function with_orientation( string $jpeg, int $orientation ): string {
		$tiff  = "II\x2A\x00\x08\x00\x00\x00";
		$tiff .= "\x01\x00\x12\x01\x03\x00\x01\x00\x00\x00";
		$tiff .= chr( $orientation ) . "\x00\x00\x00\x00\x00\x00\x00";
		$exif  = "Exif\0\0" . $tiff;

		return substr( $jpeg, 0, 2 ) . "\xFF\xE1" . pack( 'n', strlen( $exif ) + 2 ) . $exif . substr( $jpeg, 2 );
	}
}
