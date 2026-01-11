<?php

namespace App\Controller;

use App\Entity\Plat;
use App\Repository\PlatRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PlatController extends AbstractController
{
    #[Route('/plats/{page}', name: 'app_plat_index', requirements: ['page' => '\d+'], defaults: ['page' => 1])]
    public function index(PlatRepository $platRepository, $page = 1): Response
    {
        $limit = 8;
        $offset = ($page - 1) * $limit;

        $plats = $platRepository->findBy([], null, $limit, $offset);
        $totalPlats = count($platRepository->findAll());
        $totalPages = ceil($totalPlats / $limit);

        return $this->render('plat/index.html.twig', [
            'plats' => $plats,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/plats/{id}/delete', name: 'app_plat_delete', methods: ['POST'])]
    public function delete(
        Plat $plat,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$plat->getId(), $request->request->get('_token'))) {

            // 🔒 protection simple contre la suppression
            if (!$plat->getCommandeItems()->isEmpty()) {
                $this->addFlash('danger', 'Impossible de supprimer ce plat car il est déjà commandé.');
                return $this->redirectToRoute('app_plat_index');
            }

            $em->remove($plat);
            $em->flush();

            $this->addFlash('success', 'Plat supprimé avec succès.');
        }

        return $this->redirectToRoute('app_plat_index');
    }
}
