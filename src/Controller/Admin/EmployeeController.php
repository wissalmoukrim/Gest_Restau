<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/employee')]
class EmployeeController extends AbstractController
{
    #[Route('/', name: 'admin_employee_index')]
    public function index(EntityManagerInterface $em): Response
    {
        return $this->render('admin/employee/index.html.twig', [
            'users' => $em->getRepository(User::class)->findAll(),
        ]);
    }

    #[Route('/new', name: 'admin_employee_new')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // Password obligatoire en création
            $hashedPassword = $passwordHasher->hashPassword(
                $user,
                $form->get('password')->getData()
            );
            $user->setPassword($hashedPassword);

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Employé ajouté avec succès !');
            return $this->redirectToRoute('admin_employee_index');
        }

        return $this->render('admin/employee/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_employee_edit')]
    public function edit(
        User $user,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $form = $this->createForm(UserType::class, $user, [
            'is_edit' => true
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // Password optionnel en modification
            if ($form->get('password')->getData()) {
                $hashedPassword = $passwordHasher->hashPassword(
                    $user,
                    $form->get('password')->getData()
                );
                $user->setPassword($hashedPassword);
            }

            $em->flush();
            $this->addFlash('success', 'Employé modifié avec succès !');
            return $this->redirectToRoute('admin_employee_index');
        }

        return $this->render('admin/employee/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

#[Route('/{id}/delete', name: 'admin_employee_delete', methods: ['POST'])]
public function delete(
    User $user,
    Request $request,
    EntityManagerInterface $em
): Response {
    if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
        try {
            // Désactiver temporairement les contraintes de clé étrangère
            $connection = $em->getConnection();
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
            
            // Supprimer directement
            $em->remove($user);
            $em->flush();
            
            // Réactiver les contraintes
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
            
            $this->addFlash('success', 'Employé supprimé avec succès !');
            
        } catch (\Exception $e) {
            // Réactiver les contraintes en cas d'erreur
            $em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
            $this->addFlash('error', 'Erreur lors de la suppression : ' . $e->getMessage());
        }
    } else {
        $this->addFlash('error', 'Token CSRF invalide');
    }

    return $this->redirectToRoute('admin_employee_index');
}
}
