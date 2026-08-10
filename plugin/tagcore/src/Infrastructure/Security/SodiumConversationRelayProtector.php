<?php
/**
 * Sodium Conversation relay protection.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);
namespace ReturnTag\TagCore\Infrastructure\Security;

use ReturnTag\TagCore\Application\Conversation\ConversationRelayProtector;
use ReturnTag\TagCore\Application\Persistence\Value\AccessTokenDigest;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\Persistence\Value\MessageCiphertext;
use ReturnTag\TagCore\Domain\Conversation\MessageSenderRole;
use RuntimeException;

/** Applies domain-separated token HMAC and Conversation-bound AEAD. */
final readonly class SodiumConversationRelayProtector implements ConversationRelayProtector {
	private const VERSION = "\x01";
	/**
	 * Create the protector.
	 *
	 * @param ConversationRelaySecrets $secrets Key material.
	 */
	public function __construct( private ConversationRelaySecrets $secrets ) {}
	/** {@inheritDoc} */
	public function generate_token(): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encodes random bearer bytes, not executable code.
		return rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' ); }
	/**
	 * Hash one raw Token.
	 *
	 * @param string $token Raw Token.
	 * @throws RuntimeException When malformed.
	 */
	public function token_digest( string $token ): AccessTokenDigest {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_-]{43}$/D', $token ) ) {
			throw new RuntimeException( 'Conversation access is unavailable.' ); }
		return AccessTokenDigest::from_digest( hash_hmac( 'sha256', "conversation-token:v1\0" . $token, $this->secrets->token_key ) );
	}
	/**
	 * Hash one direct peer.
	 *
	 * @param string $ip_address Direct peer.
	 * @throws RuntimeException When malformed.
	 */
	public function peer_digest( string $ip_address ): LookupDigest {
		$packed = @inet_pton( trim( $ip_address ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid peers fail closed.
		if ( false === $packed ) {
			throw new RuntimeException( 'Conversation access is unavailable.' ); }
		return LookupDigest::from_digest( hash_hmac( 'sha256', "conversation-peer:v1\0" . $packed, $this->secrets->token_key ) );
	}
	/**
	 * Encrypt one Message.
	 *
	 * @param string            $message Plaintext.
	 * @param int               $conversation_id Conversation identifier.
	 * @param MessageSenderRole $role Sender role.
	 */
	public function encrypt_message( string $message, int $conversation_id, MessageSenderRole $role ): MessageCiphertext {
		$nonce = random_bytes( SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES );
		$body  = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt( $message, $this->aad( $conversation_id, $role ), $nonce, $this->secrets->message_key );
		return MessageCiphertext::from_encrypted_bytes( self::VERSION . $nonce . $body );
	}
	/**
	 * Decrypt one Message.
	 *
	 * @param MessageCiphertext $ciphertext Encrypted body.
	 * @param int               $conversation_id Conversation identifier.
	 * @param MessageSenderRole $role Sender role.
	 * @throws RuntimeException When authentication fails.
	 */
	public function decrypt_message( MessageCiphertext $ciphertext, int $conversation_id, MessageSenderRole $role ): string {
		$min = 1 + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES;
		if ( strlen( $ciphertext->value ) < $min || ! str_starts_with( $ciphertext->value, self::VERSION ) ) {
			throw new RuntimeException( 'Conversation Message is unavailable.' ); }
		$nonce = substr( $ciphertext->value, 1, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES );
		$body  = substr( $ciphertext->value, 1 + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES );
		$value = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt( $body, $this->aad( $conversation_id, $role ), $nonce, $this->secrets->message_key );
		if ( false === $value ) {
			throw new RuntimeException( 'Conversation Message is unavailable.' ); }
		return $value;
	}
	/**
	 * Build Conversation- and role-bound associated data.
	 *
	 * @param int               $conversation_id Conversation identifier.
	 * @param MessageSenderRole $role Sender role.
	 * @throws RuntimeException When identifier is invalid.
	 */
	private function aad( int $conversation_id, MessageSenderRole $role ): string {
		if ( $conversation_id < 1 ) {
			throw new RuntimeException( 'Conversation Message is unavailable.' ); }
		return "returntag:conversation-message:v1\0{$conversation_id}\0{$role->value}";
	}
}
