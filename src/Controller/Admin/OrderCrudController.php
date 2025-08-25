<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use App\Form\OrderItemType;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\OrderItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;

use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

class OrderCrudController extends AbstractCrudController
{
    private RequestStack $requestStack;
    private AdminUrlGenerator $adminUrlGenerator;
    private EntityManagerInterface $entityManager;

    public function __construct(RequestStack $requestStack, AdminUrlGenerator $adminUrlGenerator, EntityManagerInterface $entityManager)
    {
        $this->requestStack = $requestStack;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->entityManager = $entityManager;
    }

    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Orders')
            ->setPageTitle('detail', fn (Order $order) => sprintf('Order  #%d', $order->getId()))
            ->setPaginatorPageSize(10)
            ->setPaginatorRangeSize(4) 
            ->overrideTemplate('crud/detail', 'admin/order/order_detail.html.twig')
            ->overrideTemplate('crud/index', 'admin/order/index.html.twig')
            ->showEntityActionsInlined();
    }
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Order) {
            $this->updateOrderPointsAndTotals($entityInstance);
        }
        parent::persistEntity($entityManager, $entityInstance);
    }
   public function configureFilters(Filters $filters): Filters
{
    return $filters
        ->add(ChoiceFilter::new('status')
            ->setChoices([
                'Pending' => Order::STATUS_PENDING,
                'Processing' => Order::STATUS_PROCESSING,
                'Shipped' => Order::STATUS_SHIPPED,
                'Delivered' => Order::STATUS_DELIVERED,
                'Cancelled' => Order::STATUS_CANCELLED,
                'Refunded' => Order::STATUS_REFUNDED,
            ])
        );
}
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Order) {
            $this->updateOrderPointsAndTotals($entityInstance);
        }
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function ensureOrderItemsHavePrices(Order $order): void
    {
        foreach ($order->getOrderItems() as $item) {
            if ($item->getUnitPrice() === null && $item->getProductVariant() !== null) {
                $item->setUnitPrice($item->getProductVariant()->getPrice());
            }
        }
    }

    public function createNewOrderItem(Order $order): OrderItem
    {
        $orderItem = new OrderItem();
        $orderItem->setRelatedOrder($order);
        $orderItem->setQuantity(1);
        return $orderItem;
    }

    private function updateOrderPointsAndTotals(Order $order): void
    {
        foreach ($order->getOrderItems() as $item) {
            // Ensure price is set before calculating points
            if ($item->getUnitPrice() === null && $item->getProductVariant() !== null) {
                $item->setUnitPrice($item->getProductVariant()->getPrice());
            }

            // Calculate and set points for each item
            $points = $item->calculatePointsEarned();
            $item->setPointsEarned($points);
        }

        // Now, update the order's totals (both amount and points)
        $order->updateTotals();
    }
    public function configureFields(string $pageName): iterable
    {
        // Index Page Fields
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('user', 'Customer')->onlyOnIndex();
        yield IntegerField::new('orderItems.count', 'Items')->onlyOnIndex();
        yield MoneyField::new('totalAmount', 'Total')->setCurrency('EUR')->onlyOnIndex();
        yield IntegerField::new('totalPointsEarned', 'Total Points')->onlyOnIndex();
        yield ChoiceField::new('status')->onlyOnIndex();
        yield DateTimeField::new('createdAt', 'Created At')->onlyOnIndex();

        // Form Fields (New/Edit)
        yield AssociationField::new('user', 'Customer')->setColumns('col-md-6')->hideOnIndex();

                $statusField = ChoiceField::new('status')
            ->setChoices([
                'Pending' => 'pending',
                'Processing' => 'processing',
                'Shipped' => 'shipped',
                'Delivered' => 'delivered',
                'Cancelled' => 'cancelled',
            ])
            ->setColumns('col-md-6')->hideOnIndex();
        if ($pageName === Crud::PAGE_NEW) {
            $statusField
                ->setFormTypeOption('data', 'pending')
                                ->setFormTypeOption('disabled', true)
                ->setHelp('Status is automatically set to Pending for new orders.');
        }
        yield $statusField;

        yield TextareaField::new('shippingAddress')->setColumns('col-md-6')->onlyOnForms();
        yield TextareaField::new('billingAddress')->setColumns('col-md-6')->onlyOnForms();
        yield ChoiceField::new('paymentMethod')
            ->setChoices([
                'PayPal' => 'paypal',
                'Credit Card' => 'credit card',
            ])
            ->setColumns('col-md-6')->hideOnIndex();
        yield TextField::new('paymentStatus')->setColumns('col-md-6')->hideOnIndex();
        yield TextField::new('transactionId', 'Transaction ID')->setColumns('col-md-6')->hideOnIndex();

        // Subtotal and Tax Amount are now auto-calculated and not editable
        yield TextField::new('shippingFee')
            ->setLabel('Shipping Fee (auto)')
            ->setFormTypeOption('disabled', true)
            ->setColumns('col-md-6')->hideOnIndex();
        yield ChoiceField::new('currency')
            ->setChoices([
                'Euro (€)' => 'EUR',
                'Dollar ($)' => 'USD',
            ])
            ->setColumns('col-md-6')->hideOnIndex();
        // Removed Discount Amount and Currency fields
        yield ChoiceField::new('shippingProvider')
            ->setChoices([
                'DHL' => 'DHL',
                'UPS' => 'UPS',
                'Poste' => 'Poste',
                'GLS' => 'GLS',
            ])
            ->setColumns('col-md-6')->hideOnIndex();

        yield ChoiceField::new('deliveryType')
            ->setChoices([
                'Standard' => 'Standard',
                'Express' => 'Express',
                'Same-day' => 'Same-day',
            ])
            ->setColumns('col-md-6')->hideOnIndex();

        yield ChoiceField::new('destination')
            ->setChoices([
                'Domestic' => 'Domestic',
                'International' => 'International',
            ])
            ->setColumns('col-md-6')->hideOnIndex();
        // Shipping Method removed from entity
        yield TextField::new('trackingNumber')->setLabel('Tracking Number')->setColumns('col-md-6')->hideOnIndex();
        yield TextareaField::new('notes')->setLabel('Notes')->setColumns('col-md-12')->hideOnIndex();

        yield CollectionField::new('orderItems')
            ->setLabel('Order Items')
            ->setEntryIsComplex(true)
            ->setEntryType(OrderItemType::class)
            ->setFormTypeOptions(['by_reference' => false])
            ->setColumns('col-12')
            ->onlyOnForms();
    }



    public function configureActions(Actions $actions): Actions
    {
    $request = $this->requestStack->getCurrentRequest();
    $isArchivedView = $request?->query->get('show') === 'archived';

    $toggleArchivedAction = Action::new(
        $isArchivedView ? 'viewActive' : 'viewArchived',
        $isArchivedView ? 'View Active' : 'View Archived'
    )
        ->linkToUrl(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Crud::PAGE_INDEX)
                ->set('show', $isArchivedView ? null : 'archived')
                ->generateUrl()
        )
        ->createAsGlobalAction()
        ->addCssClass('btn btn-secondary');

    if ($isArchivedView) {
        $archiveOrRestoreAction = Action::new('restore', 'Restore')
            ->setIcon('fa fa-undo')
            ->setCssClass('btn btn-success btn-sm text-white action-restore')
            ->linkToCrudAction('restoreOrder')
            ->setHtmlAttributes([
                'data-bs-toggle' => 'modal',
                'data-bs-target' => '#confirmationModal',
                'data-action' => 'restore'
            ]);
        $archiveOrRestoreActionName = 'restore';
    } else {
        $archiveOrRestoreAction = Action::new('archive', 'Archive')
            ->setIcon('fa fa-archive')
            ->setCssClass('btn btn-warning btn-sm text-white')
            ->linkToCrudAction('archiveOrder')
            ->setHtmlAttributes([
                'data-bs-toggle' => 'modal',
                'data-bs-target' => '#confirmationModal',
                'data-action' => 'archive'
            ]);
        $archiveOrRestoreActionName = 'archive';
    }

    return $actions
        ->add(Crud::PAGE_INDEX, Action::DETAIL)
        ->update(Crud::PAGE_INDEX, Action::DETAIL, fn(Action $action) =>
            $action->setIcon('fa fa-eye')->setLabel('Show')
        )
        ->update(Crud::PAGE_INDEX, Action::EDIT, fn(Action $action) =>
            $action->setIcon('fa fa-edit')->setLabel('Edit')
        )
        ->remove(Crud::PAGE_INDEX, Action::DELETE)
        ->remove(Crud::PAGE_DETAIL, Action::DELETE)
        ->add(Crud::PAGE_INDEX, $archiveOrRestoreAction)
        ->add(Crud::PAGE_INDEX, $toggleArchivedAction)
        ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, $archiveOrRestoreActionName]);
}

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        if ($this->requestStack->getCurrentRequest()?->query->get('show') === 'archived') {
            $queryBuilder->andWhere('entity.deletedAt IS NOT NULL');
        } else {
            $queryBuilder->andWhere('entity.deletedAt IS NULL');
        }
        return $queryBuilder;
    }

    public function archiveOrder(AdminContext $context): Response
    {
        $order = $context->getEntity()->getInstance();
        if ($order instanceof Order) {
            $order->setDeletedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Order #%d was archived.', $order->getId()));
        }

        $url = $context->getReferrer();
        if (null === $url) {
            $url = $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl();
        }

        return $this->redirect($url);
    }

    public function restoreOrder(AdminContext $context): Response
    {
        $order = $context->getEntity()->getInstance();
        if ($order instanceof Order) {
            $order->setDeletedAt(null);
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Order #%d was restored.', $order->getId()));
        }

        $url = $context->getReferrer();
        if (null === $url) {
            $url = $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl();
        }

        return $this->redirect($url);
    }

}
