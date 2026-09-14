<?php

namespace App\Entity\Platform;

use App\Entity\Traits\CommonTrait;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Uid\Uuid;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * 数据库连接
 * 用于存储数据库连接信息
 * 包括数据库类型、主机、端口、数据库名、用户名、密码等
 */
#[ORM\Entity]
#[ORM\Table(name: "platform_database_connection")]
class DatabaseConnection
{
    use CommonTrait;

    #[ORM\Id]
  #[ORM\Column(type: "uuid", unique: true)]
  private $id;

    /**
     * 数据库连接名称
     */
    #[ORM\Column(type: "string", length: 255)]
    private string $name = '';

    /**
     * doctrine 数据库驱动
     * 例如：pdf_mysql, pdo_pgsql, etc.
     */
    #[ORM\Column(type: "string", length: 255)]
    private string $driver = '';

    /**
     * 数据库类型
     * 例如：mysql, pgsql, etc.
     */
    #[ORM\Column(type: "string", length: 255)]
    private $type;

    /**
     * 主机
     */
    #[ORM\Column(type: "string", length: 255)]
    private $host;

    /**
     * 端口
     * 例如：3306, 5432, etc.
     */
    #[ORM\Column(type: "string", length: 255)]
    private $port;

    /**
     * 数据库名
     */
    #[ORM\Column(type: "string", length: 255)]
    private $database;

    /**
     * 字符集
     * 例如：utf8mb4, etc.
     */
    #[ORM\Column(type: "string", length: 50)]
    private string $charset = 'utf8mb4';

    /**
 * 用户名 / Username
     */
    #[ORM\Column(type: "string", length: 255)]
    private $username;

    /**
     * 密码（加密存储）
     */
    #[ORM\Column(type: "text")]
    private string $passwordEncrypted = '';

    /**
     * 连接字符串
     * 例如：mysql://user:<secret>@localhost:3306/dbname?charset=utf8mb4
     */
    #[ORM\Column(type: "string", length: 1024)]
    private $dsn;

    /**
     * 数据源
     */
    #[ORM\OneToMany(targetEntity: DataSource::class, mappedBy: "databaseConnection")]
    private Collection $dataSources;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->dataSources = new ArrayCollection();
    }

        /**
     * 设置密码（自动加密）
     */
    public function setPassword(string $plainPassword): void
    {
        $encryptionKey = $_ENV['DB_CRYPT_KEY'] ?? 'default-key'; // 放在 .env
        $this->passwordEncrypted = base64_encode(
            openssl_encrypt($plainPassword, 'AES-256-CBC', $encryptionKey, 0, substr($encryptionKey, 0, 16))
        );
    }

    /**
     * 获取明文密码（自动解密）
     */
    public function getPassword(): string
    {
        $encryptionKey = $_ENV['DB_CRYPT_KEY'] ?? 'default-key';
        return openssl_decrypt(
            base64_decode($this->passwordEncrypted),
            'AES-256-CBC',
            $encryptionKey,
            0,
            substr($encryptionKey, 0, 16)
        );
    }

    /**
     * 设置 DSN 并解析为结构化字段
     */
    public function setDsn(string $dsn, string $encryptionKey): self
    {
        $this->dsn = $dsn;

        // 解析 DSN
        $parts = parse_url($dsn);
        if (!$parts) {
            throw new \InvalidArgumentException("Invalid DSN format: {$dsn}");
        }

        $this->type = $parts['scheme'] ?? '';
        $this->host = $parts['host'] ?? '';
        $this->port = $parts['port'] ?? '';
        $this->username = $parts['user'] ?? '';
        $this->database = isset($parts['path']) ? ltrim($parts['path'], '/') : '';

        // 获取 query 参数
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            $this->charset = $query['charset'] ?? 'utf8mb4';
        }

        // 加密密码
        if (!empty($parts['pass'])) {
            $this->passwordEncrypted = $this->encrypt($parts['pass'], $encryptionKey);
        }

        return $this;
    }

    /**
     * 从结构化字段生成真实 DSN（带明文密码）
     */
    public function generateDsn(string $encryptionKey): string
    {
        $password = $this->decrypt($this->passwordEncrypted, $encryptionKey);
        return sprintf(
            "%s://%s:%s@%s:%s/%s?charset=%s",
            $this->type,
            $this->username,
            $password,
            $this->host,
            $this->port,
            $this->database,
            $this->charset
        );
    }

    /**
     * 获取安全可展示的 DSN（密码隐藏）
     */
    public function getSafeDsn(): string
    {
        return sprintf(
            "%s://%s:%s@%s:%s/%s?charset=%s",
            $this->type,
            $this->username,
            '<SECRET>',
            $this->host,
            $this->port,
            $this->database,
            $this->charset
        );
    }

      /**
     * AES 加密
     */
    private function encrypt(string $data, string $key): string
    {
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $cipher);
    }

    /**
     * AES 解密
     */
    private function decrypt(string $data, string $key): string
    {
        $raw = base64_decode($data);
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        return openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    }

    /**
     * 基于结构化字段生成 DSN 字符串（用于 Doctrine DBAL）
     */
    public function buildDsn(): string
    {
        return sprintf(
            "%s:host=%s;port=%s;dbname=%s;charset=%s",
            str_replace('pdo_', '', $this->driver),
            $this->host,
            $this->port,
            $this->database,
            $this->charset
        );
    }

    /**
     * 获取原始存储的 DSN（如果存在）或生成的 DSN
     */
    public function getDsn(): string
    {
        return $this->dsn ?: $this->buildDsn();
    }

    // Getter methods
    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function setDriver(string $driver): self
    {
        $this->driver = $driver;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getHost(): ?string
    {
        return $this->host;
    }

    public function setHost(string $host): self
    {
        $this->host = $host;
        return $this;
    }

    public function getPort(): ?string
    {
        return $this->port;
    }

    public function setPort(string $port): self
    {
        $this->port = $port;
        return $this;
    }

    public function getDatabase(): ?string
    {
        return $this->database;
    }

    public function setDatabase(string $database): self
    {
        $this->database = $database;
        return $this;
    }

    public function getCharset(): string
    {
        return $this->charset;
    }

    public function setCharset(string $charset): self
    {
        $this->charset = $charset;
        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    /**
     * 获取连接模式（根据是否设置了 DSN 来判断）
     */
    public function getConnectionMode(): string
    {
        return !empty($this->dsn) ? 'dsn' : 'params';
    }

    /**
     * 获取原始存储的 DSN
     */
    public function getRawDsn(): ?string
    {
        return $this->dsn;
    }

    /**
     * 设置原始 DSN（不解析）
     */
    public function setRawDsn(?string $dsn): self
    {
        $this->dsn = $dsn;
        return $this;
    }
}
