<?php

namespace App\Service\Platform;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * API Key 加密/解密服务
 *
 * 使用 AES-256-GCM 对 LLM API Key 进行加密存储。
 * 密钥通过 LLM_ENCRYPTION_KEY 环境变量配置（base64 编码的 32 字节密钥）。
 */
class LlmEncryptor
{
    private string $cipher = 'aes-256-gcm';
    private string $key;

    public function __construct(
        #[Autowire('%env(LLM_ENCRYPTION_KEY)%')] string $key
    ) {
        $this->key = base64_decode($key);
    }

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext, $this->cipher, $this->key,
            OPENSSL_RAW_DATA, $iv, $tag, '', 16
        );
        return base64_encode($iv . $tag . $ciphertext);
    }

    public function decrypt(string $encoded): ?string
    {
        $data = base64_decode($encoded, true);
        if ($data === false || strlen($data) < 28) {
            return null;
        }
        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $ciphertext = substr($data, 28);
        $result = openssl_decrypt($ciphertext, $this->cipher, $this->key,
            OPENSSL_RAW_DATA, $iv, $tag);
        return $result === false ? null : $result;
    }
}
