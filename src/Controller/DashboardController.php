<?php

namespace App\Controller;

use App\Entity\Plat;
use App\Repository\PlatRepository;
use App\Repository\CommandeItemRepository;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'dashboard')]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        PlatRepository $platRepository,
        CommandeItemRepository $commandeItemRepository,
        CommandeRepository $commandeRepository,
        PaginatorInterface $paginator
    ): Response {
        // Créer la requête pour les plats avec pagination
        $query = $em->getRepository(Plat::class)
            ->createQueryBuilder('p')
            ->orderBy('p.id', 'DESC')
            ->getQuery();

        // Paginer les résultats
        $plats = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1), // Numéro de page par défaut
            9 // Nombre d'éléments par page
        );

        // Initialiser les variables par défaut
        $platsStats = [];
        $platPlusVendu = null;
        $revenuTotal = 0;
        $commandesAujourdhui = 0;
        $totalVentes = 0;

        // Seulement si admin
        if ($this->isGranted('ROLE_ADMIN')) {
            try {
                // Vérifier si la méthode getPlatsStats existe
                if (method_exists($platRepository, 'getPlatsStats')) {
                    // Récupérer les statistiques de consommation
                    $platsStats = $platRepository->getPlatsStats();
                } else {
                    // Méthode alternative si getPlatsStats n'existe pas
                    $platsStats = $this->getPlatsStatsAlternative($em);
                }
                
                // Calculer le total des ventes
                foreach ($platsStats as $stat) {
                    if (isset($stat['quantiteVendue'])) {
                        $totalVentes += $stat['quantiteVendue'];
                    }
                }
                
                // Plat le plus vendu
                $platPlusVendu = null;
                $maxVentes = 0;
                foreach ($platsStats as $stat) {
                    if (isset($stat['quantiteVendue']) && $stat['quantiteVendue'] > $maxVentes) {
                        $maxVentes = $stat['quantiteVendue'];
                        $platPlusVendu = $stat;
                    }
                }
                
                // Revenu total
                $revenuTotal = $commandeRepository->createQueryBuilder('c')
                    ->select('SUM(c.total)')
                    ->getQuery()
                    ->getSingleScalarResult() ?? 0;
                
                // Commandes aujourd'hui
                $aujourdhui = new \DateTime();
                $aujourdhui->setTime(0, 0, 0);
                $commandesAujourdhui = $commandeRepository->createQueryBuilder('c')
                    ->select('COUNT(c.id)')
                    ->where('c.date >= :date')
                    ->setParameter('date', $aujourdhui)
                    ->getQuery()
                    ->getSingleScalarResult() ?? 0;
                    
            } catch (\Exception $e) {
                // En cas d'erreur, on garde les valeurs par défaut
                // Souvent dû à des tables vides
                $platsStats = [];
            }
        }

        return $this->render('dashboard/index.html.twig', [
            'plats' => $plats, // Utilise l'objet paginé
            'platsStats' => $platsStats,
            'platPlusVendu' => $platPlusVendu,
            'revenuTotal' => $revenuTotal,
            'commandesAujourdhui' => $commandesAujourdhui,
            'totalVentes' => $totalVentes,
        ]);
    }

    // Méthode alternative si getPlatsStats n'existe pas
    private function getPlatsStatsAlternative(EntityManagerInterface $em): array
    {
        // Requête SQL simple pour récupérer les stats
        $conn = $em->getConnection();
        
        $sql = "
            SELECT 
                p.id,
                p.nom,
                p.prix,
                COALESCE(SUM(ci.quantite), 0) as quantiteVendue,
                COALESCE(SUM(ci.quantite * ci.prix_unitaire), 0) as revenu
            FROM plat p
            LEFT JOIN commande_item ci ON p.id = ci.plat_id
            GROUP BY p.id
            HAVING quantiteVendue > 0
            ORDER BY quantiteVendue DESC
        ";
        
        try {
            $result = $conn->executeQuery($sql)->fetchAllAssociative();
            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    // Détails d'un plat
    #[Route('/plat/{id}', name: 'plat_details')]
    public function details(Plat $plat): Response
    {
        return $this->render('dashboard/details.html.twig', [
            'plat' => $plat,
        ]);
    }
}