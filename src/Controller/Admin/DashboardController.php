<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\Product;
use App\Entity\Category;
use App\Entity\Brand;
use App\Entity\Order;
use App\Entity\Review;
use App\Entity\Color;
// Add other entities you want to manage here

use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use App\Repository\OrderRepository;
use App\Repository\OrderItemRepository;
use App\Repository\ProductRepository;
use App\Repository\CartRepository;
class DashboardController extends AbstractDashboardController
{
    private OrderRepository $orderRepo;
    private OrderItemRepository $orderItemRepo;
    private ProductRepository $productRepo;
    private CartRepository $cartRepo;

    public function __construct(
        OrderRepository $orderRepo,
        OrderItemRepository $orderItemRepo,
        ProductRepository $productRepo,
        CartRepository $cartRepo
    ) {
        $this->orderRepo = $orderRepo;
        $this->orderItemRepo = $orderItemRepo;
        $this->productRepo = $productRepo;
        $this->cartRepo = $cartRepo;
    }

    #[Route('/admin', name: 'admin_dashboard')] // This is the route our LoginRedirectSubscriber uses
    public function index(): Response {
        $now = new \DateTimeImmutable('now');
        $fromDay = $now->modify('-29 days')->setTime(0, 0);
        $toDay = $now->setTime(23, 59, 59);

        // Fetch orders in the last 30 days (exclude archived)
        $orders = $this->orderRepo->createQueryBuilder('o')
            ->andWhere('o.deletedAt IS NULL')
            ->andWhere('o.createdAt BETWEEN :from AND :to')
            ->setParameter('from', $fromDay)
            ->setParameter('to', $toDay)
            ->orderBy('o.createdAt', 'ASC')
            ->getQuery()->getResult();

        $completed = [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED];

        // Initialize daily revenue buckets for the last 30 days
        $revenueDaily = [];
        $labels = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = $now->modify("-{$i} days")->format('Y-m-d');
            $labels[] = $d;
            $revenueDaily[$d] = 0.0;
        }

        $totalRevenue30 = 0.0;
        $completedCount = 0;
        $orderCount30 = \count($orders);
        $aovCount = 0;

        foreach ($orders as $o) {
            $day = $o->getCreatedAt()?->format('Y-m-d');
            if (!$day) { continue; }
            $amount = (float) $o->getFinalTotal();
            // Convert USD orders back to EUR for unified reporting (approximate)
            if ($o->getCurrency() === 'USD') {
                $rate = (float) $o->getConversionRate(); // EUR->USD
                if ($rate > 0) { $amount = $amount / $rate; }
            }
            if (\in_array(strtolower((string) $o->getStatus()), $completed, true)) {
                if (isset($revenueDaily[$day])) {
                    $revenueDaily[$day] += $amount;
                }
                $totalRevenue30 += $amount;
                $completedCount++;
                $aovCount++;
            }
        }

        $avgOrderValue = $aovCount > 0 ? $totalRevenue30 / $aovCount : 0.0;
        $completionRate = $orderCount30 > 0 ? round(($completedCount / $orderCount30) * 100, 2) : null;
        // Cart -> Order conversion (last 30 days)
        $cartsWithItems30 = (int) ($this->cartRepo->createQueryBuilder('c')
            ->innerJoin('c.cartItems', 'ci')
            ->andWhere('c.createdAt BETWEEN :from AND :to')
            ->setParameter('from', $fromDay)
            ->setParameter('to', $toDay)
            ->select('COUNT(DISTINCT c.id)')
            ->getQuery()->getSingleScalarResult());
        $conversionRate = $cartsWithItems30 > 0 ? round(($completedCount / $cartsWithItems30) * 100, 2) : null;

        // Monthly revenue (last 12 months)
        $fromMonth = (new \DateTimeImmutable('first day of this month'))->modify('-11 months')->setTime(0, 0);
        $orders12 = $this->orderRepo->createQueryBuilder('o2')
            ->andWhere('o2.deletedAt IS NULL')
            ->andWhere('o2.createdAt BETWEEN :fromM AND :toM')
            ->setParameter('fromM', $fromMonth)
            ->setParameter('toM', $toDay)
            ->orderBy('o2.createdAt', 'ASC')
            ->getQuery()->getResult();
        $revenueMonthly = [];
        $monthLabels = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = $now->modify("-{$i} months")->format('Y-m');
            $monthLabels[] = $m;
            $revenueMonthly[$m] = 0.0;
        }
        foreach ($orders12 as $o) {
            $m = $o->getCreatedAt()?->format('Y-m');
            if (!$m) { continue; }
            if (!\in_array(strtolower((string) $o->getStatus()), $completed, true)) { continue; }
            $amount = (float) $o->getFinalTotal();
            if ($o->getCurrency() === 'USD') {
                $rate = (float) $o->getConversionRate();
                if ($rate > 0) { $amount = $amount / $rate; }
            }
            if (isset($revenueMonthly[$m])) { $revenueMonthly[$m] += $amount; }
        }

        // Top-selling products (last 30 days, completed orders)
        $topSelling = $this->orderItemRepo->createQueryBuilder('oi')
            ->innerJoin('oi.relatedOrder', 'o')
            ->leftJoin('oi.product', 'p')
            ->andWhere('o.deletedAt IS NULL')
            ->andWhere('o.createdAt BETWEEN :from AND :to')
            ->andWhere('LOWER(o.status) IN (:sts)')
            ->setParameter('from', $fromDay)
            ->setParameter('to', $toDay)
            ->setParameter('sts', array_map('strtolower', $completed))
            ->select('p.name AS name, SUM(oi.quantity) AS qty')
            ->groupBy('p.id')
            ->orderBy('qty', 'DESC')
            ->setMaxResults(5)
            ->getQuery()->getArrayResult();

        // Low stock alerts (consider variants if they exist)
        $products = $this->productRepo->createQueryBuilder('p')
            ->andWhere('p.deletedAt IS NULL')
            ->getQuery()->getResult();
        $lowStock = [];
        $lowThreshold = 10;
        foreach ($products as $p) {
            // If there are variants, use their total; else use product stock
            $variantCount = method_exists($p, 'getProductVariants') ? $p->getProductVariants()->count() : 0;
            $stock = $variantCount > 0 ? (int) $p->getTotalStock() : (int) ($p->getQuantityInStock() ?? 0);
            if ($stock <= $lowThreshold) {
                $lowStock[] = [ 'name' => (string) $p, 'stock' => $stock ];
            }
        }
        usort($lowStock, fn($a, $b) => $a['stock'] <=> $b['stock']);
        $lowStock = array_slice($lowStock, 0, 10);

        // Latest orders
        $latestOrders = $this->orderRepo->createQueryBuilder('o')
            ->andWhere('o.deletedAt IS NULL')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()->getResult();

        // Processing time stats (approx: createdAt -> updatedAt for completed orders, last 60 days)
        $from60 = $now->modify('-60 days');
        $procOrders = $this->orderRepo->createQueryBuilder('o')
            ->andWhere('o.deletedAt IS NULL')
            ->andWhere('o.createdAt >= :from60')
            ->andWhere('LOWER(o.status) IN (:sts)')
            ->setParameter('from60', $from60)
            ->setParameter('sts', array_map('strtolower', $completed))
            ->getQuery()->getResult();
        $durations = [];
        foreach ($procOrders as $o) {
            $c = $o->getCreatedAt();
            $u = $o->getUpdatedAt() ?: $o->getCreatedAt();
            if ($c && $u) {
                $delta = $u->getTimestamp() - $c->getTimestamp();
                if ($delta >= 0) { $durations[] = $delta; }
            }
        }
        $avgHrs = null; $medianHrs = null;
        if (\count($durations) > 0) {
            $avgHrs = round(array_sum($durations) / \count($durations) / 3600, 1);
            sort($durations);
            $mid = (int) floor(\count($durations) / 2);
            if (\count($durations) % 2 === 0) {
                $medianHrs = round((($durations[$mid - 1] + $durations[$mid]) / 2) / 3600, 1);
            } else {
                $medianHrs = round($durations[$mid] / 3600, 1);
            }
        }

        // Cancellations and refunds (last 30 days)
        $badSts = [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED];
        $badOrders = $this->orderRepo->createQueryBuilder('o')
            ->andWhere('o.deletedAt IS NULL')
            ->andWhere('o.createdAt BETWEEN :from AND :to')
            ->andWhere('LOWER(o.status) IN (:bad)')
            ->setParameter('from', $fromDay)
            ->setParameter('to', $toDay)
            ->setParameter('bad', array_map('strtolower', $badSts))
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()->getResult();
        $cancelCount = 0; $refundCount = 0; $cancelAmount = 0.0; $refundAmount = 0.0;
        foreach ($badOrders as $o) {
            $amt = (float) $o->getFinalTotal();
            if ($o->getCurrency() === 'USD') {
                $rate = (float) $o->getConversionRate();
                if ($rate > 0) { $amt = $amt / $rate; }
            }
            $s = strtolower((string) $o->getStatus());
            if ($s === Order::STATUS_CANCELLED) { $cancelCount++; $cancelAmount += $amt; }
            if ($s === Order::STATUS_REFUNDED) { $refundCount++; $refundAmount += $amt; }
        }

        // Inventory turnover (approx): units sold last 30 days vs current stock
        $turnover = [];
        $unitsByProduct = $this->orderItemRepo->createQueryBuilder('oi')
            ->innerJoin('oi.relatedOrder', 'o')
            ->leftJoin('oi.product', 'p')
            ->andWhere('o.deletedAt IS NULL')
            ->andWhere('o.createdAt BETWEEN :from AND :to')
            ->andWhere('LOWER(o.status) IN (:sts)')
            ->setParameter('from', $fromDay)
            ->setParameter('to', $toDay)
            ->setParameter('sts', array_map('strtolower', $completed))
            ->select('p.id AS pid, p.name AS name, SUM(oi.quantity) AS units')
            ->groupBy('p.id')
            ->orderBy('units', 'DESC')
            ->setMaxResults(10)
            ->getQuery()->getArrayResult();
        $productIndex = [];
        foreach ($products as $p) { $productIndex[$p->getId()] = $p; }
        foreach ($unitsByProduct as $row) {
            $pid = (int) ($row['pid'] ?? 0);
            $units = (int) ($row['units'] ?? 0);
            $p = $productIndex[$pid] ?? null;
            if ($p) {
                $variantCount = method_exists($p, 'getProductVariants') ? $p->getProductVariants()->count() : 0;
                $stock = $variantCount > 0 ? (int) $p->getTotalStock() : (int) ($p->getQuantityInStock() ?? 0);
                $ratio = $stock + $units > 0 ? round($units / max(1, $stock), 2) : null;
                $turnover[] = [ 'name' => (string) $p, 'unitsSold30' => $units, 'stock' => $stock, 'ratio' => $ratio ];
            }
        }

        // Most viewed but out-of-stock (requires Product.views)
        $popularProducts = $this->productRepo->createQueryBuilder('p')
            ->andWhere('p.deletedAt IS NULL')
            ->orderBy('p.views', 'DESC')
            ->setMaxResults(100)
            ->getQuery()->getResult();
        $mostViewedOOS = [];
        foreach ($popularProducts as $p) {
            $variantCount = method_exists($p, 'getProductVariants') ? $p->getProductVariants()->count() : 0;
            $stock = $variantCount > 0 ? (int) $p->getTotalStock() : (int) ($p->getQuantityInStock() ?? 0);
            $views = method_exists($p, 'getViews') ? (int) $p->getViews() : 0;
            if ($stock <= 0 && $views > 0) {
                $mostViewedOOS[] = ['name' => (string) $p, 'views' => $views];
            }
            if (count($mostViewedOOS) >= 10) { break; }
        }
        $hasViewTracking = true;

        return $this->render('admin/dashboard.html.twig', [
            'kpi' => [
                'totalRevenue30' => $totalRevenue30,
                'avgOrderValue' => $avgOrderValue,
                'completionRate' => $completionRate,
                'conversionRate' => $conversionRate,
                'ordersCount30' => $orderCount30,
                'currency' => 'EUR',
            ],
            'revenueDailyLabels' => $labels,
            'revenueDailyValues' => array_values($revenueDaily),
            'revenueMonthlyLabels' => $monthLabels,
            'revenueMonthlyValues' => array_values($revenueMonthly),
            'topSelling' => $topSelling,
            'lowStock' => $lowStock,
            'latestOrders' => $latestOrders,
            'processing' => [ 'avgHours' => $avgHrs, 'medianHours' => $medianHrs ],
            'cancellations' => [ 'count' => $cancelCount, 'amount' => $cancelAmount ],
            'refunds' => [ 'count' => $refundCount, 'amount' => $refundAmount ],
            'turnover' => $turnover,
            'hasViewTracking' => $hasViewTracking,
            'mostViewedOOS' => $mostViewedOOS,
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('OptiView Store Admin');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToRoute('Back to Site', 'fas fa-undo', 'app_home');

        yield MenuItem::section('Users');
        yield MenuItem::linkToCrud('Customers', 'fas fa-users', User::class)->setController(UserCrudController::class);
        // Add ->setController(UserCrudController::class) if you create a custom CRUD controller

        yield MenuItem::section('Catalog');
        yield MenuItem::linkToCrud('Products', 'fas fa-boxes-stacked', Product::class)->setController(ProductCrudController::class);
        yield MenuItem::linkToCrud('Categories', 'fas fa-tags', Category::class)->setController(CategoryCrudController::class);
        yield MenuItem::linkToCrud('Brands', 'fas fa-building', Brand::class)->setController(BrandCrudController::class);
        yield MenuItem::linkToCrud('Colors', 'fas fa-palette', Color::class)->setController(ColorCrudController::class);
        // Add more catalog entities like Style, Shape, Genre here

        yield MenuItem::linkToCrud('Styles', 'fas fa-paint-brush', \App\Entity\Style::class)->setController(\App\Controller\Admin\StyleCrudController::class);
        yield MenuItem::linkToCrud('Shapes', 'fas fa-shapes', \App\Entity\Shape::class)->setController(\App\Controller\Admin\ShapeCrudController::class);
        yield MenuItem::linkToCrud('Genres', 'fas fa-glasses', \App\Entity\Genre::class)->setController(\App\Controller\Admin\GenreCrudController::class);
        yield MenuItem::linkToCrud('Product Offers', 'fas fa-tags', \App\Entity\ProductOffer::class)->setController(\App\Controller\Admin\ProductOfferCrudController::class);

        yield MenuItem::section('Orders & Reviews');
        yield MenuItem::linkToCrud('Orders', 'fas fa-shopping-cart', Order::class)->setController(OrderCrudController::class);
        yield MenuItem::linkToCrud('Reviews', 'fas fa-star', Review::class);

        yield MenuItem::section('Blog');
        yield MenuItem::linkToCrud('Posts', 'fas fa-newspaper', \App\Entity\Post::class)->setController(\App\Controller\Admin\PostCrudController::class);
        yield MenuItem::linkToCrud('Categories', 'fas fa-folder', \App\Entity\Categorypost::class)->setController(\App\Controller\Admin\CategorypostCrudController::class);
        yield MenuItem::linkToCrud('Tags', 'fas fa-tag', \App\Entity\Tag::class)->setController(\App\Controller\Admin\TagCrudController::class);
        yield MenuItem::linkToCrud('Comments', 'fas fa-comments', \App\Entity\Comment::class)->setController(\App\Controller\Admin\CommentCrudController::class);

        // Add more sections and entities as needed
    }
   public function configureAssets(): Assets
    {
        return Assets::new()->addCssFile('admin/easyadmin-custom.css');
    }
}
