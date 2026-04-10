<?php

namespace App\Entity\Organization;

use App\Repository\Organization\EmployeeRepository;
use App\Annotation\Ef;
use App\Entity\Traits\CommonTrait;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Uid\Uuid;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * 员工信息
 * isBusinessEntity
 */
#[ORM\Entity(repositoryClass: EmployeeRepository::class)]
#[ORM\Table(name: 'org_employee')]
#[ORM\HasLifecycleCallbacks]
class Employee implements UserInterface, PasswordAuthenticatedUserInterface
{
    use CommonTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\OneToMany(mappedBy: 'employee', targetEntity: \App\Entity\Security\WebauthnCredential::class)]
    private Collection $passkeys;

    /**
     * 工号
     * @Ef(
     *     group="employee_base_info",
     *     isBF=true
     * )
     */
    #[ORM\Column(type: 'string', length: 50, unique: true)]
    private $employeeNo;

    /**
     * 姓名
     * @Ef(
     *     group="employee_base_info",
     *     isBF=true
     * )
     */
    #[ORM\Column(type: 'string', length: 100)]
    private $name;

    /**
     * 头像
     * @Ef(
     *     group="employee_base_info",
     *     isBF=true
     * )
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $avatar;

    /**
     * 英文名
     * @Ef(
     *     group="employee_base_info",
     *     isBF=true
     * )
     */
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $englishName;

    /**
     * 用户名
     * @Ef(
     *     group="employee_account_info",
     *     isBF=true
     * )
     */
    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private $username;

    /**
     * 密码
     * @Ef(
     *     group="employee_account_info",
     *     isBF=true
     * )
     */
    #[ORM\Column(type: 'string', length: 255)]
    private $password;

    /**
     * 强制重置密码标记（首次登录时）
     * @Ef(
     *     group="employee_account_info",
     *     isBF=false
     * )
     */
    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $forcePasswordReset = true;

    /**
     * 邮箱
     * @Ef(
     *     group="employee_contact_info",
     *     isBF=true
     * )
     */
    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private $email;

    /**
     * 手机号
     * @Ef(
     *     group="employee_contact_info",
     *     isBF=true
     * )
     */
    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private $mobile;

    /**
     * 性别
     * @Ef(
     *     group="employee_base_info",
     *     isBF=true
     * )
     */
    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    private $gender;

    /**
     * 出生日期
     * @Ef(
     *     group="employee_base_info",
     *     isBF=true
     * )
     */
    #[ORM\Column(type: 'date', nullable: true)]
    private $birthDate;

    /**
     * 身份证号
     * @Ef(
     *     group="employee_base_info",
     *     isBF=true
     * )
     */
    #[ORM\Column(type: 'string', length: 18, nullable: true)]
    private $idCard;

    /**
     * 入职日期
     * @Ef(
     *     group="employee_job_info",
     *     isBF=true
     * )
     */
    #[ORM\Column(type: 'date', nullable: true)]
    private $hireDate;

    /**
     * 离职日期
     * @Ef(
     *     group="employee_job_info",
     *     isBF=true
     * )
     */
    #[ORM\Column(type: 'date', nullable: true)]
    private $terminationDate;

    /**
     * 在职状态（在职/离职）
     * @Ef(
     *     group="employee_job_info",
     *     isBF=true
     * )
     */
    #[ORM\Column(type: 'string', length: 20)]
    private $employmentStatus = 'active';

    /**
     * 工作状态（工作/休假/出差/外出/会议中）
     * @Ef(
     *     group="employee_job_info",
     *     isBF=true
     * )
     */
    #[ORM\Column(type: 'string', length: 20)]
    private $workStatus = 'working';

    /**
     * 所属公司
     * @Ef(
     *     group="employee_org_info",
     *     isBF=true
     * )
     */
    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id')]
    private $company;

    /**
     * 所属部门
     * @Ef(
     *     group="employee_org_info",
     *     isBF=true
     * )
     */
    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(name: 'department_id', referencedColumnName: 'id')]
    private $department;

    /**
     * 岗位
     * @Ef(
     *     group="employee_job_info",
     *     isBF=true
     * )
     */
    #[ORM\ManyToOne(targetEntity: Position::class)]
    #[ORM\JoinColumn(name: 'position_id', referencedColumnName: 'id')]
    private $position;

    /**
     * 直接上级
     * @Ef(
     *     group="employee_org_info",
     *     isBF=true
     * )
     */
    #[ORM\ManyToOne(targetEntity: Employee::class, inversedBy: 'subordinates')]
    #[ORM\JoinColumn(name: 'manager_id', referencedColumnName: 'id', nullable: true)]
    private $manager;

    /**
     * 下属
     */
    #[ORM\OneToMany(mappedBy: 'manager', targetEntity: Employee::class)]
    private $subordinates;

    /**
     * 用户角色
     */
    #[ORM\Column(type: 'json')]
    private $roles = [];

    /**
     * 是否为系统账号 (不属于正常员工，不在花名册展示)
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private $isSystem = false;

    /**
     * 最后登录时间
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    private $lastLoginAt;

    /**
     * 密码是否由用户自己修改过
     * @Ef(
     *     group="employee_account_info",
     *     isBF=true
     * )
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private $isPasswordModifiedByUser = false;

    /**
     * 账户是否启用
     * @Ef(
     *     group="employee_account_info",
     *     isBF=true
     * )
     */
    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private $isActive = true;

    /**
     * 紧急联系人
     * @Ef(
     *     group="employee_contact_info",
     *     isBF=true
     * )
     */
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $emergencyContact;

    /**
     * 紧急联系人电话
     * @Ef(
     *     group="employee_contact_info",
     *     isBF=true
     * )
     */
    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private $emergencyPhone;

    /**
     * 最高学历
     * @Ef(
     *     group="employee_education_info",
     *     isBF=true
     * )
     */
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $education;

    /**
     * 毕业院校
     * @Ef(
     *     group="employee_education_info",
     *     isBF=true
     * )
     */
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $school;

    /**
     * 专业
     * @Ef(
     *     group="employee_education_info",
     *     isBF=true
     * )
     */
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $major;

    /**
     * 安全问题答案
     * Store as JSON: {"question1": "answer1", "question2": "answer2"}
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private $securityAnswers = [];

    public function getSecurityAnswers(): ?array
    {
        return $this->securityAnswers;
    }

    public function setSecurityAnswers(?array $securityAnswers): self
    {
        $this->securityAnswers = $securityAnswers;
        return $this;
    }

    /**
     * 毕业时间
     * @Ef(
     *     group="employee_education_info",
     *     isBF=true
     * )
     */
    #[ORM\Column(type: 'date', nullable: true)]
    private $graduationDate;

    /**
     * 家庭住址
     * @Ef(
     *     group="employee_contact_info",
     *     isBF=true
     * )
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $address;

    /**
     * Managed Departments
     */
    #[ORM\ManyToMany(targetEntity: Department::class, inversedBy: 'manager')]
    #[ORM\JoinTable(name: 'org_department_managers')]
    private $managedDepartments;

    public function __construct()
    {
        // 自动生成 UUID
        $this->id = Uuid::v4();
        $this->subordinates = new ArrayCollection();
        $this->passkeys = new ArrayCollection();
        $this->managedDepartments = new ArrayCollection();
    }

    /**
     * Get the value of id
     */
    public function getId(): ?Uuid
    {
        return $this->id;
    }

    /**
     * Set the value of id
     *
     * @return  self
     */
    public function setId(?Uuid $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get 工号
     */
    public function getEmployeeNo()
    {
        return $this->employeeNo;
    }

    /**
     * Set 工号
     *
     * @return  self
     */
    public function setEmployeeNo($employeeNo)
    {
        $this->employeeNo = $employeeNo;

        return $this;
    }

    /**
     * Get 姓名
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set 姓名
     *
     * @return  self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get 英文名
     */
    public function getEnglishName()
    {
        return $this->englishName;
    }

    /**
     * Set 英文名
     *
     * @return  self
     */
    public function setEnglishName($englishName)
    {
        $this->englishName = $englishName;

        return $this;
    }

    /**
     * Get 用户名
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Set 用户名
     *
     * @return  self
     */
    public function setUsername($username)
    {
        $this->username = $username;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function isForcePasswordReset(): bool
    {
        return $this->forcePasswordReset;
    }

    public function setForcePasswordReset(bool $forcePasswordReset): self
    {
        $this->forcePasswordReset = $forcePasswordReset;

        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): self
    {
        $this->avatar = $avatar;
        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // 确保每个用户至少有 ROLE_USER 角色
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // 如果存储了任何临时的、敏感的数据，可以在这里清除
        // 示例：清空明文密码
        // $this->plainPassword = null;
    }

    /**
     * Get 邮箱
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Set 邮箱
     *
     * @return  self
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get 手机号
     */
    public function getMobile()
    {
        return $this->mobile;
    }

    /**
     * Set 手机号
     *
     * @return  self
     */
    public function setMobile($mobile)
    {
        $this->mobile = $mobile;

        return $this;
    }

    /**
     * Get 性别
     */
    public function getGender()
    {
        return $this->gender;
    }

    /**
     * Set 性别
     *
     * @return  self
     */
    public function setGender($gender)
    {
        $this->gender = $gender;

        return $this;
    }

    /**
     * Get 出生日期
     */
    public function getBirthDate()
    {
        return $this->birthDate;
    }

    /**
     * Set 出生日期
     *
     * @return  self
     */
    public function setBirthDate($birthDate)
    {
        $this->birthDate = $birthDate;

        return $this;
    }

    /**
     * Get 身份证号
     */
    public function getIdCard()
    {
        return $this->idCard;
    }

    /**
     * Set 身份证号
     *
     * @return  self
     */
    public function setIdCard($idCard)
    {
        $this->idCard = $idCard;

        return $this;
    }

    /**
     * Get 入职日期
     */
    public function getHireDate()
    {
        return $this->hireDate;
    }

    public function getIsPasswordModifiedByUser(): bool
    {
        return $this->isPasswordModifiedByUser;
    }

    public function setIsPasswordModifiedByUser(bool $isPasswordModifiedByUser): self
    {
        $this->isPasswordModifiedByUser = $isPasswordModifiedByUser;

        return $this;
    }

    /**
     * Set 入职日期
     *
     * @return  self
     */
    public function setHireDate($hireDate)
    {
        $this->hireDate = $hireDate;

        return $this;
    }

    /**
     * Get 离职日期
     */
    public function getTerminationDate()
    {
        return $this->terminationDate;
    }

    /**
     * Set 离职日期
     *
     * @return  self
     */
    public function setTerminationDate($terminationDate)
    {
        $this->terminationDate = $terminationDate;

        return $this;
    }

    /**
     * Get 在职状态
     */
    public function getEmploymentStatus()
    {
        return $this->employmentStatus;
    }

    /**
     * Set 在职状态
     *
     * @return  self
     */
    public function setEmploymentStatus($employmentStatus)
    {
        $this->employmentStatus = $employmentStatus;

        // 离职自动停用账户，在职自动启用账户
        if ($employmentStatus === 'inactive') {
            $this->isActive = false;
        } elseif ($employmentStatus === 'active') {
            $this->isActive = true;
        }

        return $this;
    }

    /**
     * Get 工作状态
     */
    public function getWorkStatus()
    {
        return $this->workStatus;
    }

    /**
     * Set 工作状态
     *
     * @return  self
     */
    public function setWorkStatus($workStatus)
    {
        $this->workStatus = $workStatus;

        return $this;
    }

    /**
     * 兼容旧代码，获取状态（返回在职状态）
     */
    public function getStatus()
    {
        return $this->employmentStatus;
    }

    /**
     * 兼容旧代码，设置状态（设置在职状态）
     */
    public function setStatus($status)
    {
        return $this->setEmploymentStatus($status);
    }

    /**
     * Get 所属公司
     */
    public function getCompany()
    {
        return $this->company;
    }

    /**
     * Set 所属公司
     *
     * @return  self
     */
    public function setCompany($company)
    {
        $this->company = $company;

        return $this;
    }

    /**
     * Get 所属部门
     */
    public function getDepartment()
    {
        return $this->department;
    }

    /**
     * Set 所属部门
     *
     * @return  self
     */
    public function setDepartment($department)
    {
        $this->department = $department;

        return $this;
    }

    /**
     * Get 岗位
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * Set 岗位
     *
     * @return  self
     */
    public function setPosition($position)
    {
        $this->position = $position;

        return $this;
    }

    /**
     * Get 直接上级
     */
    public function getManager()
    {
        return $this->manager;
    }

    /**
     * Set 直接上级
     *
     * @return  self
     */
    public function setManager($manager)
    {
        $this->manager = $manager;

        return $this;
    }

    /**
     * Get 下属
     */
    public function getSubordinates(): Collection
    {
        return $this->subordinates;
    }

    /**
     * @return Collection<int, \App\Entity\Security\WebauthnCredential>
     */
    public function getPasskeys(): Collection
    {
        return $this->passkeys;
    }

    public function addPasskey(\App\Entity\Security\WebauthnCredential $passkey): self
    {
        if (!$this->passkeys->contains($passkey)) {
            $this->passkeys[] = $passkey;
            $passkey->setEmployee($this);
        }

        return $this;
    }

    public function removePasskey(\App\Entity\Security\WebauthnCredential $passkey): self
    {
        if ($this->passkeys->removeElement($passkey)) {
            // set the owning side to null (unless already changed)
            if ($passkey->getEmployee() === $this) {
                $passkey->setEmployee(null);
            }
        }

        return $this;
    }

    /**
     * Get 最后登录时间
     */
    public function getLastLoginAt()
    {
        return $this->lastLoginAt;
    }

    /**
     * Set 最后登录时间
     *
     * @return  self
     */
    public function setLastLoginAt($lastLoginAt)
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }

    /**
     * Get 是否为系统账号
     */
    public function getIsSystem(): bool
    {
        return $this->isSystem;
    }

    /**
     * Set 是否为系统账号
     */
    public function setIsSystem(bool $isSystem): self
    {
        $this->isSystem = $isSystem;

        return $this;
    }

    /**
     * Get 账户是否启用
     */
    public function getIsActive()
    {
        return $this->isActive;
    }

    /**
     * Set 账户是否启用
     *
     * @return  self
     */
    public function setIsActive($isActive)
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * Get 紧急联系人
     */
    public function getEmergencyContact()
    {
        return $this->emergencyContact;
    }

    /**
     * Set 紧急联系人
     *
     * @return  self
     */
    public function setEmergencyContact($emergencyContact)
    {
        $this->emergencyContact = $emergencyContact;

        return $this;
    }

    /**
     * Get 紧急联系人电话
     */
    public function getEmergencyPhone()
    {
        return $this->emergencyPhone;
    }

    /**
     * Set 紧急联系人电话
     *
     * @return  self
     */
    public function setEmergencyPhone($emergencyPhone)
    {
        $this->emergencyPhone = $emergencyPhone;

        return $this;
    }

    /**
     * Get 最高学历
     */
    public function getEducation()
    {
        return $this->education;
    }

    /**
     * Set 最高学历
     *
     * @return  self
     */
    public function setEducation($education)
    {
        $this->education = $education;

        return $this;
    }

    /**
     * Get 毕业院校
     */
    public function getSchool()
    {
        return $this->school;
    }

    /**
     * Set 毕业院校
     *
     * @return  self
     */
    public function setSchool($school)
    {
        $this->school = $school;

        return $this;
    }

    /**
     * Get 专业
     */
    public function getMajor()
    {
        return $this->major;
    }

    /**
     * Set 专业
     *
     * @return  self
     */
    public function setMajor($major)
    {
        $this->major = $major;

        return $this;
    }

    /**
     * Get 毕业时间
     */
    public function getGraduationDate()
    {
        return $this->graduationDate;
    }

    /**
     * Set 毕业时间
     *
     * @return  self
     */
    public function setGraduationDate($graduationDate)
    {
        $this->graduationDate = $graduationDate;

        return $this;
    }

    /**
     * Get 家庭住址
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * Set 家庭住址
     *
     * @return  self
     */
    public function setAddress($address)
    {
        $this->address = $address;

        return $this;
    }
}
