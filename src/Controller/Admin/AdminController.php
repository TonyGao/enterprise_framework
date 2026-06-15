<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AdminController extends BaseController
{
  #[Route('/admin/index', name: 'admin_index')]
  public function index(): Response
  {
    return $this->render('admin/index.html.twig');
  }
}
