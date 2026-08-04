<?php
/**
 * GD Finder evidence processor.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Media;

use ErrorException;
use GdImage;
use ReturnTag\TagCore\Application\FinderReport\FinderEvidenceDerivative;
use ReturnTag\TagCore\Application\FinderReport\FinderEvidenceImageProcessor;
use ReturnTag\TagCore\Application\FinderReport\FinderEvidenceProcessingException;
use ReturnTag\TagCore\Application\FinderReport\FinderEvidenceSource;
use ReturnTag\TagCore\Application\FinderReport\ProcessedFinderEvidence;
use ReturnTag\TagCore\Application\Persistence\Value\MediaDigest;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceMime;
use RuntimeException;
use Throwable;

/**
 * Verifies exact containers, decodes one still image, and emits stripped JPEGs.
 */
final class GdFinderEvidenceImageProcessor implements FinderEvidenceImageProcessor {
	/**
	 * Fail closed when required in-memory image functions are unavailable.
	 *
	 * @throws RuntimeException When GD or Fileinfo is unavailable.
	 */
	public function __construct() {
		if ( ! function_exists( 'imagecreatefromstring' ) || ! function_exists( 'imagejpeg' ) || ! class_exists( 'finfo' ) ) {
			throw new RuntimeException( 'Finder evidence image processing is unavailable.' );
		}
	}

	/**
	 * Process one bounded untrusted image.
	 *
	 * @param FinderEvidenceSource $source Untrusted bounded bytes.
	 * @throws FinderEvidenceProcessingException When validation or processing fails.
	 */
	public function process( FinderEvidenceSource $source ): ProcessedFinderEvidence {
		$mime = $this->detect_mime( $source->bytes );
		$this->assert_exact_signature( $source->bytes, $mime );

		try {
			$size = $this->image_size( $source->bytes );

			if ( ! is_array( $size ) || ! isset( $size[0], $size[1], $size['mime'] ) || $size['mime'] !== $mime->value ) {
				throw new FinderEvidenceProcessingException( 'Finder evidence image is invalid.' );
			}

			$source_width  = (int) $size[0];
			$source_height = (int) $size[1];
			$this->assert_pixel_budget( $source_width, $source_height );
			$image      = $this->decode( $source->bytes );
			$normalized = $this->orient_and_flatten( $image, $this->jpeg_orientation( $source->bytes, $mime ) );
			imagedestroy( $image );
			$review = $this->review_derivative( $normalized );
			$email  = $this->email_derivative( $normalized );
			imagedestroy( $normalized );

			return new ProcessedFinderEvidence(
				$mime,
				strlen( $source->bytes ),
				$source_width,
				$source_height,
				MediaDigest::from_digest( hash( 'sha256', $source->bytes ) ),
				$review,
				$email
			);
		} catch ( FinderEvidenceProcessingException $exception ) {
			throw $exception;
		} catch ( Throwable ) {
			throw new FinderEvidenceProcessingException( 'Finder evidence image is invalid.' );
		}
	}

	/**
	 * Detect MIME from bytes only.
	 *
	 * @param string $bytes Source bytes.
	 * @throws FinderEvidenceProcessingException When the MIME is unsupported.
	 */
	private function detect_mime( string $bytes ): FinderEvidenceMime {
		$finfo = new \finfo( FILEINFO_MIME_TYPE );
		$value = $finfo->buffer( $bytes );
		$mime  = is_string( $value ) ? FinderEvidenceMime::tryFrom( strtolower( $value ) ) : null;

		if ( null === $mime ) {
			throw new FinderEvidenceProcessingException( 'Finder evidence image is invalid.' );
		}

		return $mime;
	}

	/**
	 * Reject appended bytes, malformed lengths, and animated containers.
	 *
	 * @param string             $bytes Source bytes.
	 * @param FinderEvidenceMime $mime Detected MIME.
	 * @throws FinderEvidenceProcessingException When the container is malformed.
	 */
	private function assert_exact_signature( string $bytes, FinderEvidenceMime $mime ): void {
		$valid = match ( $mime ) {
			FinderEvidenceMime::JPEG => strlen( $bytes ) >= 4 && str_starts_with( $bytes, "\xFF\xD8" ) && str_ends_with( $bytes, "\xFF\xD9" ),
			FinderEvidenceMime::PNG => $this->is_exact_still_png( $bytes ),
			FinderEvidenceMime::WEBP => $this->is_exact_still_webp( $bytes ),
		};

		if ( ! $valid ) {
			throw new FinderEvidenceProcessingException( 'Finder evidence image is invalid.' );
		}
	}

	/**
	 * Validate PNG chunks and reject animation control data.
	 *
	 * @param string $bytes Source bytes.
	 */
	private function is_exact_still_png( string $bytes ): bool {
		if ( strlen( $bytes ) < 20 || ! str_starts_with( $bytes, "\x89PNG\r\n\x1A\n" ) ) {
			return false;
		}

		$offset = 8;
		$length = strlen( $bytes );

		while ( $offset + 12 <= $length ) {
			$chunk_length = unpack( 'Nlength', substr( $bytes, $offset, 4 ) );

			if ( ! is_array( $chunk_length ) || ! isset( $chunk_length['length'] ) ) {
				return false;
			}

			$size = (int) $chunk_length['length'];
			$type = substr( $bytes, $offset + 4, 4 );
			$next = $offset + 12 + $size;

			if ( $next > $length || 'acTL' === $type ) {
				return false;
			}

			if ( 'IEND' === $type ) {
				return 0 === $size && $next === $length;
			}

			$offset = $next;
		}

		return false;
	}

	/**
	 * Validate RIFF length and reject animated WebP chunks.
	 *
	 * @param string $bytes Source bytes.
	 */
	private function is_exact_still_webp( string $bytes ): bool {
		if ( strlen( $bytes ) < 20 || ! str_starts_with( $bytes, 'RIFF' ) || 'WEBP' !== substr( $bytes, 8, 4 ) ) {
			return false;
		}

		$length = unpack( 'Vlength', substr( $bytes, 4, 4 ) );

		if ( ! is_array( $length ) || ! isset( $length['length'] ) || strlen( $bytes ) - 8 !== (int) $length['length'] ) {
			return false;
		}

		$offset = 12;
		$total  = strlen( $bytes );

		while ( $offset + 8 <= $total ) {
			$type       = substr( $bytes, $offset, 4 );
			$chunk_size = unpack( 'Vlength', substr( $bytes, $offset + 4, 4 ) );

			if ( ! is_array( $chunk_size ) || ! isset( $chunk_size['length'] ) || in_array( $type, array( 'ANIM', 'ANMF' ), true ) ) {
				return false;
			}

			$size    = (int) $chunk_size['length'];
			$offset += 8 + $size + ( $size % 2 );
		}

		return $offset === $total;
	}

	/**
	 * Enforce the decoded 20-megapixel budget without integer overflow.
	 *
	 * @param int $width Source width.
	 * @param int $height Source height.
	 * @throws FinderEvidenceProcessingException When dimensions are invalid.
	 */
	private function assert_pixel_budget( int $width, int $height ): void {
		if ( $width < 1 || $height < 1 || $width > 20000000 || $width > intdiv( 20000000, $height ) ) {
			throw new FinderEvidenceProcessingException( 'Finder evidence image is invalid.' );
		}
	}

	/**
	 * Read server-decoded dimensions while containing library warnings.
	 *
	 * @param string $bytes Source bytes.
	 * @return array<int|string, int|string>|false
	 */
	private function image_size( string $bytes ): array|false {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Converts decoder warnings into a privacy-safe fixed failure.
		set_error_handler(
			static function (): never {
				throw new ErrorException( 'Image inspection failed.' );
			}
		);

		try {
			return getimagesizefromstring( $bytes );
		} finally {
			restore_error_handler();
		}
	}

	/**
	 * Decode bytes while converting library warnings into a fixed failure.
	 *
	 * @param string $bytes Source bytes.
	 * @throws FinderEvidenceProcessingException When decoding fails.
	 */
	private function decode( string $bytes ): GdImage {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Converts decoder warnings into a privacy-safe fixed failure.
		set_error_handler(
			static function (): never {
				throw new ErrorException( 'Image decode failed.' );
			}
		);

		try {
			$image = imagecreatefromstring( $bytes );
		} finally {
			restore_error_handler();
		}

		if ( false === $image ) {
			throw new FinderEvidenceProcessingException( 'Finder evidence image is invalid.' );
		}

		return $image;
	}

	/**
	 * Apply JPEG orientation and flatten pixels onto an opaque white canvas.
	 *
	 * @param GdImage $image Decoded source.
	 * @param int     $orientation EXIF orientation from 1 through 8.
	 * @throws FinderEvidenceProcessingException When pixel normalization fails.
	 */
	private function orient_and_flatten( GdImage $image, int $orientation ): GdImage {
		$oriented = $this->apply_orientation( $image, $orientation );
		$canvas   = imagecreatetruecolor( imagesx( $oriented ), imagesy( $oriented ) );

		if ( false === $canvas ) {
			throw new FinderEvidenceProcessingException( 'Finder evidence image is invalid.' );
		}

		$white = imagecolorallocate( $canvas, 255, 255, 255 );

		if ( false === $white ) {
			throw new FinderEvidenceProcessingException( 'Finder evidence image is invalid.' );
		}

		imagefill( $canvas, 0, 0, $white );
		imagecopy( $canvas, $oriented, 0, 0, 0, 0, imagesx( $oriented ), imagesy( $oriented ) );

		if ( $oriented !== $image ) {
			imagedestroy( $oriented );
		}

		return $canvas;
	}

	/**
	 * Apply the eight EXIF orientation transforms.
	 *
	 * @param GdImage $image Decoded source.
	 * @param int     $orientation EXIF orientation.
	 * @throws FinderEvidenceProcessingException When a rotation fails.
	 */
	private function apply_orientation( GdImage $image, int $orientation ): GdImage {
		if ( in_array( $orientation, array( 2, 4, 5, 7 ), true ) ) {
			$mode = in_array( $orientation, array( 2, 5 ), true ) ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL;
			imageflip( $image, $mode );
		}

		$angle = match ( $orientation ) {
			3, 4 => 180,
			5, 6 => -90,
			7, 8 => 90,
			default => 0,
		};

		if ( 0 === $angle ) {
			return $image;
		}

		$rotated = imagerotate( $image, $angle, 0 );

		if ( false === $rotated ) {
			throw new FinderEvidenceProcessingException( 'Finder evidence image is invalid.' );
		}

		return $rotated;
	}

	/**
	 * Create the review derivative.
	 *
	 * @param GdImage $image Normalized source pixels.
	 */
	private function review_derivative( GdImage $image ): FinderEvidenceDerivative {
		$encoded = $this->encode_jpeg( $image, 1600, 88 );

		return FinderEvidenceDerivative::review( $encoded['bytes'], $encoded['width'], $encoded['height'] );
	}

	/**
	 * Create a derivative satisfying both email bounds.
	 *
	 * @param GdImage $image Normalized source pixels.
	 * @throws FinderEvidenceProcessingException When no bounded encoding can be produced.
	 */
	private function email_derivative( GdImage $image ): FinderEvidenceDerivative {
		foreach ( array( 800, 720, 640, 560, 480, 400, 320 ) as $edge ) {
			foreach ( array( 82, 74, 66, 58, 50, 42, 34 ) as $quality ) {
				$encoded = $this->encode_jpeg( $image, $edge, $quality );

				if ( strlen( $encoded['bytes'] ) <= 204800 ) {
					return FinderEvidenceDerivative::email( $encoded['bytes'], $encoded['width'], $encoded['height'] );
				}
			}
		}

		throw new FinderEvidenceProcessingException( 'Finder evidence image is invalid.' );
	}

	/**
	 * Resize and encode a metadata-free JPEG in memory.
	 *
	 * @param GdImage $image Normalized source pixels.
	 * @param int     $maximum_edge Maximum edge.
	 * @param int     $quality JPEG quality.
	 * @return array{bytes: string, width: int, height: int}
	 * @throws FinderEvidenceProcessingException When resizing or encoding fails.
	 */
	private function encode_jpeg( GdImage $image, int $maximum_edge, int $quality ): array {
		$source_width  = imagesx( $image );
		$source_height = imagesy( $image );
		$scale         = min( 1, $maximum_edge / max( $source_width, $source_height ) );
		$width         = max( 1, (int) floor( $source_width * $scale ) );
		$height        = max( 1, (int) floor( $source_height * $scale ) );
		$derivative    = imagecreatetruecolor( $width, $height );

		if ( false === $derivative || ! imagecopyresampled( $derivative, $image, 0, 0, 0, 0, $width, $height, $source_width, $source_height ) ) {
			throw new FinderEvidenceProcessingException( 'Finder evidence image is invalid.' );
		}

		ob_start();
		$encoded = imagejpeg( $derivative, null, $quality );
		$bytes   = ob_get_clean();
		imagedestroy( $derivative );

		if ( ! $encoded || '' === $bytes ) {
			throw new FinderEvidenceProcessingException( 'Finder evidence image is invalid.' );
		}

		return array(
			'bytes'  => $bytes,
			'width'  => $width,
			'height' => $height,
		);
	}

	/**
	 * Read only the JPEG EXIF orientation tag from source bytes.
	 *
	 * @param string             $bytes Source bytes.
	 * @param FinderEvidenceMime $mime Source MIME.
	 */
	private function jpeg_orientation( string $bytes, FinderEvidenceMime $mime ): int {
		if ( FinderEvidenceMime::JPEG !== $mime ) {
			return 1;
		}

		$offset = 2;
		$length = strlen( $bytes );

		while ( $offset + 4 <= $length && "\xFF" === $bytes[ $offset ] ) {
			$marker = ord( $bytes[ $offset + 1 ] );
			$size   = unpack( 'nsize', substr( $bytes, $offset + 2, 2 ) );

			if ( ! is_array( $size ) || ! isset( $size['size'] ) || (int) $size['size'] < 2 ) {
				return 1;
			}

			$segment_size = (int) $size['size'];

			if ( $offset + 2 + $segment_size > $length ) {
				return 1;
			}

			if ( 0xE1 === $marker ) {
				$orientation = $this->tiff_orientation( substr( $bytes, $offset + 4, $segment_size - 2 ) );

				if ( null !== $orientation ) {
					return $orientation;
				}
			}

			if ( 0xDA === $marker ) {
				break;
			}

			$offset += 2 + $segment_size;
		}

		return 1;
	}

	/**
	 * Parse the bounded TIFF orientation entry from one EXIF segment.
	 *
	 * @param string $segment JPEG APP1 payload.
	 */
	private function tiff_orientation( string $segment ): ?int {
		if ( strlen( $segment ) < 14 || ! str_starts_with( $segment, "Exif\0\0" ) ) {
			return null;
		}

		$tiff       = substr( $segment, 6 );
		$little_end = str_starts_with( $tiff, 'II' );

		if ( ! $little_end && ! str_starts_with( $tiff, 'MM' ) ) {
			return null;
		}

		$ifd_offset = $this->binary_integer( substr( $tiff, 4, 4 ), $little_end, 4 );

		if ( null === $ifd_offset || $ifd_offset + 2 > strlen( $tiff ) ) {
			return null;
		}

		$count = $this->binary_integer( substr( $tiff, $ifd_offset, 2 ), $little_end, 2 );

		if ( null === $count || $count > 256 ) {
			return null;
		}

		for ( $index = 0; $index < $count; ++$index ) {
			$entry = $ifd_offset + 2 + ( $index * 12 );

			if ( $entry + 12 > strlen( $tiff ) ) {
				return null;
			}

			$tag  = $this->binary_integer( substr( $tiff, $entry, 2 ), $little_end, 2 );
			$type = $this->binary_integer( substr( $tiff, $entry + 2, 2 ), $little_end, 2 );

			if ( 0x0112 === $tag && 3 === $type ) {
				$value = $this->binary_integer( substr( $tiff, $entry + 8, 2 ), $little_end, 2 );

				return null !== $value && $value >= 1 && $value <= 8 ? $value : null;
			}
		}

		return null;
	}

	/**
	 * Decode a two- or four-byte TIFF integer.
	 *
	 * @param string $bytes Binary integer.
	 * @param bool   $little_end Whether TIFF uses little endian.
	 * @param int    $size Expected byte count.
	 */
	private function binary_integer( string $bytes, bool $little_end, int $size ): ?int {
		if ( strlen( $bytes ) !== $size ) {
			return null;
		}

		$format = 2 === $size ? ( $little_end ? 'vvalue' : 'nvalue' ) : ( $little_end ? 'Vvalue' : 'Nvalue' );
		$value  = unpack( $format, $bytes );

		return is_array( $value ) && isset( $value['value'] ) ? (int) $value['value'] : null;
	}
}
