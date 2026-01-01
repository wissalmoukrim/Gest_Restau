<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request, 
        UserPasswordHasherInterface $passwordHasher, 
        EntityManagerInterface $em
    ): Response
    {
        
        if ($this->getUser()) {
           return $this->redirectToRoute('client_dashboard');

        }

       
        $user = new User();

        // إنشاء form من RegistrationFormType وربطه بالـ User
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        // معالجة البيانات بعد submit
        if ($form->isSubmitted() && $form->isValid()) {
            // تشفير كلمة المرور
            $user->setPassword(
                $passwordHasher->hashPassword($user, $form->get('plainPassword')->getData())
            );

            // إعطاء دور المستخدم العادي
            $user->setRoles(['ROLE_CLIENT']);

            // حفظ المستخدم فـ database
            $em->persist($user);
            $em->flush();

            // redirect للصفحة الرئيسية بعد التسجيل
            return $this->redirectToRoute('client_dashboard');
        }

        // عرض form
        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}
