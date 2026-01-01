<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // 1️⃣ إذا المستخدم مسجّل الدخول بالفعل
        if ($this->getUser()) {
            $roles = $this->getUser()->getRoles();

            if (in_array('ROLE_ADMIN', $roles, true)) {
                return $this->redirectToRoute('admin_dashboard');
            }

            if (in_array('ROLE_CLIENT', $roles, true)) {
                return $this->redirectToRoute('client_dashboard');
            }
        }

        // 2️⃣ جلب آخر اسم مستخدم خطأ إذا كان
        $lastUsername = $authenticationUtils->getLastUsername();
        $error = $authenticationUtils->getLastAuthenticationError();

        // 3️⃣ عرض login form
        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        // Symfony يدير logout تلقائيًا، لا داعي لأي كود هنا
        throw new \Exception('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
