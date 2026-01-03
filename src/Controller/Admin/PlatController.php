<?php

namespace App\Controller\Admin;

use App\Entity\Plat;
use App\Form\PlatType;
use App\Repository\PlatRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class PlatController extends AbstractController
{
    #[Route('/admin/plat', name: 'app_admin_plat')]
    public function index(PlatRepository $platRepository): Response
    {
        // 1️⃣ Récupérer tous les plats
        $plats = $platRepository->findAll();

        // 2️⃣ Envoyer à Twig
        return $this->render('admin/plat/index.html.twig', [
            'plats' => $plats,
        ]);
    }

    #[Route('/admin/plat/new', name: 'app_admin_plat_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        // 1️⃣ Nouveau plat
        $plat = new Plat();

        // 2️⃣ Formulaire
        $form = $this->createForm(PlatType::class, $plat);
        $form->handleRequest($request);

        // 3️⃣ Sauvegarde
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($plat);
            $em->flush();

            return $this->redirectToRoute('app_admin_plat');
        }

        return $this->render('admin/plat/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/plat/{id}/edit', name: 'app_admin_plat_edit')]
    public function edit(
        Request $request,
        Plat $plat,
        EntityManagerInterface $em
    ): Response {
        // 1️⃣ Formulaire pré-rempli
        $form = $this->createForm(PlatType::class, $plat);
        $form->handleRequest($request);

        // 2️⃣ Update
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('app_admin_plat');
        }

        return $this->render('admin/plat/edit.html.twig', [
            'form' => $form->createView(),
            'plat' => $plat,
        ]);
    }

    #[Route('/admin/plat/{id}/delete', name: 'app_admin_plat_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Plat $plat,
        EntityManagerInterface $em
    ): RedirectResponse {
        //  Sécurité CSRF
        if ($this->isCsrfTokenValid('delete_plat_' . $plat->getId(), $request->request->get('_token'))) {
            $em->remove($plat);
            $em->flush();
        }

        return $this->redirectToRoute('app_admin_plat');
    }
}
