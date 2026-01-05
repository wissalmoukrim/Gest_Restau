<?php

namespace App\Controller\Admin;

use App\Entity\Plat;
use App\Form\PlatType;
use App\Repository\PlatRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/plat')]
class PlatController extends AbstractController
{

    
    #[Route('', name: 'app_admin_plat')]
    public function index(PlatRepository $platRepository): Response
    {
        $plats = $platRepository->findAll();
        return $this->render('admin/plat/index.html.twig', compact('plats'));
    }

    #[Route('/test-save', name: 'app_admin_test_save')]
    public function testSave(EntityManagerInterface $em): Response
    {
        try {
            // Création directe sans formulaire
            $plat = new Plat();
            $plat->setNom('Test Direct Controller');
            $plat->setPrix('29.99');
            $plat->setDisponible(true);
            $plat->setDescription('Test depuis le contrôleur');
            
            $em->persist($plat);
            $em->flush();
            
            $id = $plat->getId();
            
            return new Response("
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Test Save</title>
                    <style>
                        body { font-family: Arial; padding: 20px; }
                        .success { background: #4CAF50; color: white; padding: 20px; margin: 10px 0; }
                    </style>
                </head>
                <body>
                    <h1>Test Sauvegarde Doctrine</h1>
                    <div class='success'>
                        ✅ PLAT CRÉÉ AVEC SUCCÈS<br>
                        ID généré: $id<br>
                        Nom: Test Direct Controller<br>
                        Prix: 29.99 DH
                    </div>
                    <a href='/admin/plat'>Voir la liste des plats</a><br>
                    <a href='javascript:location.reload()'>Refaire le test</a>
                </body>
                </html>
            ");
            
        } catch (\Exception $e) {
            return new Response("
                <div style='background: red; color: white; padding: 20px;'>
                    ❌ ERREUR: " . $e->getMessage() . "
                </div>
            ");
        }
    }

    #[Route('/new', name: 'app_admin_plat_new')]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $plat = new Plat();
        $form = $this->createForm(PlatType::class, $plat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                
                // CORRECTION: Utilise l'extension du nom original au lieu de guessExtension()
                $extension = pathinfo($imageFile->getClientOriginalName(), PATHINFO_EXTENSION);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . strtolower($extension);

                $imageFile->move(
                    $this->getParameter('uploads_directory'),
                    $newFilename
                );

                $plat->setImage($newFilename);
            }

            $em->persist($plat);
            $em->flush();

            $this->addFlash('success', 'Plat ajouté avec succès');
            return $this->redirectToRoute('app_admin_plat');
        }

        return $this->render('admin/plat/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

#[Route('/edit/{id}', name: 'app_admin_plat_edit')]
public function edit(Plat $plat, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
{
    $form = $this->createForm(PlatType::class, $plat);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $imageFile = $form->get('image')->getData();
        if ($imageFile) {
            $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            
            // Même correction ici
            $extension = pathinfo($imageFile->getClientOriginalName(), PATHINFO_EXTENSION);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . strtolower($extension);

            $imageFile->move(
                $this->getParameter('uploads_directory'),
                $newFilename
            );

            $plat->setImage($newFilename);
        }

        $em->flush();
        $this->addFlash('success', 'Plat modifié avec succès');
        return $this->redirectToRoute('app_admin_plat');
    }

    return $this->render('admin/plat/edit.html.twig', [
        'form' => $form->createView(),
        'plat' => $plat, // AJOUTE CETTE LIGNE
    ]);
}

    #[Route('/delete/{id}', name: 'app_admin_plat_delete', methods:['POST'])]
    public function delete(Plat $plat, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_plat_'.$plat->getId(), $request->request->get('_token'))) {
            $em->remove($plat);
            $em->flush();
            $this->addFlash('success', 'Plat supprimé avec succès');
        }

        return $this->redirectToRoute('app_admin_plat');
    }
}