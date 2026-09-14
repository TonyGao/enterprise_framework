<?php

namespace App\Controller\Api;

use App\Entity\Organization\Employee;
use App\Service\Storage\FileUploadService;
use App\Service\Storage\FileUrlGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use App\Service\MailService;
use App\Entity\System\EmailFunctionBinding;

#[Route('/api/employee')]
#[IsGranted('ROLE_USER')]
class AvatarController extends AbstractController
{
    private FileUploadService $fileUploadService;
    private EntityManagerInterface $em;
    private FileUrlGenerator $fileUrlGenerator;

    public function __construct(FileUploadService $fileUploadService, EntityManagerInterface $em, FileUrlGenerator $fileUrlGenerator)
    {
        $this->fileUploadService = $fileUploadService;
        $this->em = $em;
        $this->fileUrlGenerator = $fileUrlGenerator;
    }

    #[Route('/{id}/avatar', name: 'api_employee_avatar_upload', methods: ['POST'])]
    public function upload(string $id, Request $request, HubInterface $hub): Response
    {
        $currentUser = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN') && (!$currentUser instanceof Employee || (string) $currentUser->getId() !== $id)) {
            return $this->json(['status' => 'error', 'message' => 'Access denied'], 403);
        }

        $employee = $this->em->getRepository(Employee::class)->find($id);
        if (!$employee) {
            return $this->json(['status' => 'error', 'message' => 'Employee not found'], 404);
        }

        $file = $request->files->get('avatar');
        if (!$file) {
            return $this->json(['status' => 'error', 'message' => 'No file uploaded'], 400);
        }

        try {
            $uploadedFile = $this->fileUploadService->upload($file, [
                'optimize' => true,
                'max_size' => 5242880,
                'allowed_mime_types' => [
                    'image/gif',
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                ],
            ]);

            $url = $this->fileUrlGenerator->getUrl($uploadedFile);
            
            $employee->setAvatar($url);
            $this->em->flush();

            // Broadcast the new avatar via Mercure SSE
            $update = new Update(
                '/entity/employee/' . $employee->getId(),
                json_encode([
                    'type' => 'sync',
                    'entity' => 'Employee',
                    'id' => $employee->getId(),
                    'name' => $employee->getName(),
                    'avatarUrl' => $url
                ])
            );
            $hub->publish($update);

            return $this->json([
                'status' => 'success',
                'success' => true,
                'url' => $url,
            ]);

        } catch (\RuntimeException $e) {
            return $this->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => $translator->trans('msg.avatar.upload_failed') . $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine()
            ], 500);
        }
    }

    #[Route('/{id}/avatar', name: 'api_employee_avatar_remove', methods: ['DELETE'])]
    public function remove(string $id, HubInterface $hub): Response
    {
        $currentUser = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN') && (!$currentUser instanceof Employee || (string) $currentUser->getId() !== $id)) {
            return $this->json(['status' => 'error', 'message' => 'Access denied'], 403);
        }

        $employee = $this->em->getRepository(Employee::class)->find($id);
        if (!$employee) {
            return $this->json(['status' => 'error', 'message' => 'Employee not found'], 404);
        }

        $employee->setAvatar(null);
        $this->em->flush();

        // Broadcast the avatar removal via Mercure SSE
            $update = new Update(
                '/entity/employee/' . $employee->getId(),
                json_encode([
                    'type' => 'sync',
                    'entity' => 'Employee',
                    'id' => $employee->getId(),
                    'name' => $employee->getName(),
                    'avatarUrl' => null
                ])
            );
        $hub->publish($update);

        return $this->json([
            'status' => 'success',
            'success' => true
        ]);
    }

    #[Route('/{id}/send-verification', name: 'api_employee_send_verification', methods: ['POST'])]
    public function sendVerificationEmail(string $id, MailService $mailService): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['status' => 'error', 'message' => 'Access denied. Administrator privileges required.'], 403);
        }

        $employee = $this->em->getRepository(Employee::class)->find($id);
        if (!$employee) {
            return $this->json(['status' => 'error', 'message' => 'Employee not found'], 404);
        }

        if (!$employee->getEmail()) {
            return $this->json(['status' => 'error', 'message' => 'This employee does not have an email address configured.'], 400);
        }

        try {
            // "employee.verification" must match the code in EmailConfigController::SYSTEM_EMAIL_FUNCTIONS
            $mailService->sendForFunction(
                $employee->getEmail(),
                'employee.verification',
                [
                    'name' => $employee->getName(),
                    'email' => $employee->getEmail(),
                    'employeeNo' => $employee->getEmployeeNo(),
                    // Pass a generated token or link here, for demonstration we pass a mock token
                    'token' => substr(md5(uniqid()), 0, 8),
                    'expire_minutes' => 30
                ]
            );

            return $this->json(['status' => 'success', 'success' => true, 'message' => 'Verification email queued successfully.']);
        } catch (\DomainException $e) {
            // Translates binding exceptions
            return $this->json(['status' => 'error', 'message' => $translator->trans('msg.avatar.mail_not_configured') . $e->getMessage()], 400);
        } catch (\Exception $e) {
            return $this->json(['status' => 'error', 'message' => 'msg.avatar.mail_send_failed' . get_class($e) . '): ' . $e->getMessage()], 500);
        }
    }
}
