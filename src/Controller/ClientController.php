<?php

namespace App\Controller;

use App\Entity\Plat;
use App\Entity\Panier;
use App\Entity\PanierItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/client')]
class ClientController extends AbstractController
{
    #[Route('/plat', name: 'client_plat')]
    public function plat(EntityManagerInterface $em): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        $user = $this->getUser();
        $plats = $em->getRepository(Plat::class)->findAll();

        $panier = $em->getRepository(Panier::class)->findOneBy(['user' => $user]);
        if (!$panier) {
            $panier = new Panier();
            $panier->setUser($user);
            $em->persist($panier);
            $em->flush();
        }

        return $this->render('client/plat.html.twig', [
            'plats' => $plats,
            'panier' => $panier
        ]);
    }

    private function calculerPanier(Panier $panier): array
    {
        $total = 0;
        $items = [];
        foreach ($panier->getPanierItems() as $i) {
            $sub = $i->getQuantity() * $i->getPlat()->getPrix();
            $items[] = [
                'id' => $i->getId(),
                'nom' => $i->getPlat()->getNom(),
                'prix' => $i->getPlat()->getPrix(),
                'quantity' => $i->getQuantity(),
                'sub' => $sub
            ];
            $total += $sub;
        }
        return ['items' => $items, 'total' => $total, 'totalItems' => count($items)];
    }

    #[Route('/panier/add/{id}', name: 'client_panier_add', methods: ['POST'])]
    public function addToPanier(Plat $plat, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $panier = $em->getRepository(Panier::class)->findOneBy(['user' => $user]);

        if (!$panier) {
            $panier = new Panier();
            $panier->setUser($user);
            $em->persist($panier);
        }

        $panierItem = $em->getRepository(PanierItem::class)->findOneBy(['panier'=>$panier,'plat'=>$plat]);

        if (!$panierItem) {
            $panierItem = new PanierItem();
            $panierItem->setPanier($panier);
            $panierItem->setPlat($plat);
            $panierItem->setQuantity(1);
            $em->persist($panierItem);
        } else {
            $panierItem->setQuantity($panierItem->getQuantity() + 1);
        }

        $em->flush();

        return $this->json($this->calculerPanier($panier));
    }

    #[Route('/panier/update/{id}/{quantity}', name: 'client_panier_update', methods: ['POST'])]
    public function updatePanier(PanierItem $item, int $quantity, EntityManagerInterface $em): JsonResponse
    {
        if ($quantity <= 0) {
            $em->remove($item);
        } else {
            $item->setQuantity($quantity);
        }
        $em->flush();

        return $this->json($this->calculerPanier($item->getPanier()));
    }

    #[Route('/panier/remove/{id}', name: 'client_panier_remove', methods: ['POST'])]
    public function removeFromPanier(PanierItem $item, EntityManagerInterface $em): JsonResponse
    {
        $panier = $item->getPanier();
        $em->remove($item);
        $em->flush();

        return $this->json($this->calculerPanier($panier));
    }

    #[Route('/panier/vider', name: 'client_panier_vider', methods: ['POST'])]
    public function viderPanier(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $panier = $em->getRepository(Panier::class)->findOneBy(['user' => $user]);

        if ($panier) {
            foreach ($panier->getPanierItems() as $item) {
                $em->remove($item);
            }
            $em->flush();
        }

        return $this->json(['items'=>[], 'total'=>0, 'totalItems'=>0]);
    }
}
