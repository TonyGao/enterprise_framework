<?php

namespace App\Service\Security;

use App\Entity\Organization\Employee;
use App\Repository\Organization\EmployeeRepository;
use App\Repository\Security\WebauthnCredentialRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\Bundle\Security\Storage\Item;
use Webauthn\Bundle\Security\Storage\OptionsStorage;
use Webauthn\Bundle\Service\PublicKeyCredentialRequestOptionsFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * Passkey 重认证：为已登录用户生成"断言（assertion）"选项并校验其 Passkey，
 * 用于敏感操作（如重置其他管理员密码）前的二次验证——避免"忘记密码还要输密码"的悖论。
 *
 * Passkey re-authentication: generate a WebAuthn assertion challenge for the
 * currently-logged-in user and verify it, as a second factor for sensitive actions.
 */
class PasskeyReauthService
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly PublicKeyCredentialRequestOptionsFactory $optionsFactory,
        private readonly OptionsStorage $optionsStorage,
        private readonly AuthenticatorAssertionResponseValidator $assertionValidator,
        private readonly WebauthnCredentialRepository $credentialRepository,
        private readonly EmployeeRepository $employeeRepository,
        #[Autowire('%kernel.secret%')] private readonly string $secret,
    ) {
    }

    /** 当前用户是否已注册 passkey / whether the user has any passkey registered */
    public function hasPasskey(Employee $user): bool
    {
        $userEntity = $this->employeeRepository->findOneByUsername($user->getUsername());

        return $userEntity !== null
            && $this->credentialRepository->findAllForUserEntity($userEntity) !== [];
    }

    /**
     * 生成针对当前用户 passkey 的断言选项（存 challenge 以便校验）/
     * create assertion options scoped to the current user's credentials.
     *
     * @return array|null 前端可直接用的 options 数组；无 passkey 返回 null
     */
    public function createOptions(Employee $user): ?array
    {
        $userEntity = $this->employeeRepository->findOneByUsername($user->getUsername());
        if ($userEntity === null) {
            return null;
        }
        $records = $this->credentialRepository->findAllForUserEntity($userEntity);
        if ($records === []) {
            return null;
        }

        $allowCredentials = array_map(
            static fn ($record) => $record->getPublicKeyCredentialDescriptor(),
            $records,
        );

        $options = $this->optionsFactory->create('default', $allowCredentials, 'required');
        $this->optionsStorage->store(Item::create($options, $userEntity));

        $data = $this->serializer->normalize($options, 'json', [
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
        ]);

        return is_array($data) ? $data : null;
    }

    /**
     * 校验前端提交的断言。成功返回 true；失败抛异常。
     */
    public function verify(string $json, string $host): bool
    {
        $publicKeyCredential = $this->serializer->deserialize($json, PublicKeyCredential::class, 'json');
        $response = $publicKeyCredential->response;
        if (!$response instanceof AuthenticatorAssertionResponse) {
            return false;
        }

        $item = $this->optionsStorage->get($response->clientDataJSON->challenge);
        $options = $item->getPublicKeyCredentialOptions();
        if (!$options instanceof PublicKeyCredentialRequestOptions) {
            return false;
        }

        $credentialSource = $this->credentialRepository->findOneByCredentialId($publicKeyCredential->rawId);
        if ($credentialSource === null) {
            return false;
        }

        $this->assertionValidator->check(
            $credentialSource,
            $response,
            $options,
            $host,
            $item->getPublicKeyCredentialUserEntity()?->id,
        );

        return true;
    }

    /**
     * 校验通过后签发一个短时效、绑定目标管理员的签名令牌（无状态，不依赖 session）/
     * issue a short-lived HMAC token bound to the target admin after successful verification.
     */
    public function issueResetToken(string $targetId, int $ttl = 300): string
    {
        $payload = $this->base64UrlEncode((string) json_encode([
            'id' => $targetId,
            'exp' => time() + $ttl,
        ]));
        $sig = hash_hmac('sha256', $payload, $this->secret);

        return $payload . '.' . $sig;
    }

    /** 校验重置令牌：签名有效、未过期、且绑定同一目标管理员 / validate the reset token. */
    public function validateResetToken(string $token, string $targetId): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return false;
        }
        [$payload, $sig] = $parts;
        if (!hash_equals(hash_hmac('sha256', $payload, $this->secret), $sig)) {
            return false;
        }
        $data = json_decode($this->base64UrlDecode($payload), true);
        if (!is_array($data)) {
            return false;
        }

        return (string) ($data['id'] ?? '') === $targetId
            && (int) ($data['exp'] ?? 0) >= time();
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $data = strtr($data, '-_', '+/');
        $pad = strlen($data) % 4;
        if ($pad) {
            $data .= str_repeat('=', 4 - $pad);
        }

        return (string) base64_decode($data, true);
    }
}
