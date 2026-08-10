<?php
/**
 * Conversation relay key material.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);
namespace ReturnTag\TagCore\Infrastructure\Security;

use RuntimeException;

/** Loads independent external encryption and token-HMAC keys. */
final readonly class ConversationRelaySecrets {
	public const MESSAGE_KEY_NAME = 'RETURNTAG_TAGCORE_CONVERSATION_MESSAGE_KEY_V1';
	public const TOKEN_KEY_NAME   = 'RETURNTAG_TAGCORE_CONVERSATION_TOKEN_KEY_V1';
	/**
	 * Create validated independent keys.
	 *
	 * @param string $message_key Message key.
	 * @param string $token_key Token key.
	 * @throws RuntimeException When key material is invalid.
	 */
	private function __construct( public string $message_key, public string $token_key ) {
		if ( 32 !== strlen( $message_key ) || 32 !== strlen( $token_key ) || hash_equals( $message_key, $token_key ) ) {
			throw new RuntimeException( 'Conversation relay security configuration is invalid.' );
		}
	}
	/** Load external key material. */
	public static function load(): self {
		return new self( self::read( self::MESSAGE_KEY_NAME ), self::read( self::TOKEN_KEY_NAME ) ); }
	/**
	 * Build injectable test keys.
	 *
	 * @param string $message_key Message key.
	 * @param string $token_key Token key.
	 */
	public static function from_keys( string $message_key, string $token_key ): self {
		return new self( $message_key, $token_key ); }
	/**
	 * Read one Base64 external key.
	 *
	 * @param string $name Configuration name.
	 * @throws RuntimeException When configuration is unavailable.
	 */
	private static function read( string $name ): string {
		$value = defined( $name ) ? constant( $name ) : getenv( $name );
		if ( ! is_string( $value ) || '' === $value ) {
			throw new RuntimeException( 'Conversation relay security configuration is unavailable.' ); }
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- External binary key material.
		$key = base64_decode( $value, true );
		if ( ! is_string( $key ) ) {
			throw new RuntimeException( 'Conversation relay security configuration is invalid.' ); }
		return $key;
	}
}
