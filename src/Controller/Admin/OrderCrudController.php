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
            ->setPageTitle('detail', fn (Order $order) => sprintf('Order #%d', $order->getId()))
            ->setPaginatorPageSize(10) // Number of orders per page
            ->setPaginatorRangeSize(4) // Number of page links to show
            // ->overrideTemplate('crud/detail', 'admin/order/order_detail.html.twig')
            ->overrideTemplate('crud/index', 'admin/order/index.html.twig')
            ->showEntityActionsInlined(); 
    }
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Order) {
            $this->ensureOrderItemsHavePrices($entityInstance);
            $this->updateOrderTotal($entityInstance);
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
            $this->ensureOrderItemsHavePrices($entityInstance);
            $this->updateOrderTotal($entityInstance);
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

    private function updateOrderTotal(Order $order): void
    {
        $total = '0.00';
        
        foreach ($order->getOrderItems() as $item) {
            if ($item->getUnitPrice() === null) {
                throw new \RuntimeException('Order item is missing unit price');
            }
            $total = bcadd($total, bcmul($item->getQuantity(), $item->getUnitPrice(), 2), 2);
        }
        
        $order->setTotalAmount($total);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('user', 'Customer')
            ->setColumns('col-md-6')
            ->setRequired(true);
            
        $statusField = ChoiceField::new('status')
            ->setChoices([
                'Pending' => Order::STATUS_PENDING,
                'Processing' => Order::STATUS_PROCESSING,
                'Shipped' => Order::STATUS_SHIPPED,
                'Delivered' => Order::STATUS_DELIVERED,
                'Cancelled' => Order::STATUS_CANCELLED,
                'Refunded' => Order::STATUS_REFUNDED,
            ])
            ->setColumns('col-md-6');
            
        if ($pageName === Crud::PAGE_NEW) {
            // Set default value and make the field disabled
            $statusField
                ->setFormTypeOption('data', Order::STATUS_PENDING)
                ->setFormTypeOption('disabled', true)
                ->setHelp('Status is set to Pending for new orders. You can change it after creation.');
        }
            
        yield $statusField;

        yield CollectionField::new('orderItems')
            ->setEntryType(OrderItemType::class)
            ->setFormTypeOption('by_reference', false)
            ->allowAdd()
            ->allowDelete()
            ->setEntryIsComplex()
            ->setRequired(true)
            ->renderExpanded()
            ->setColumns('col-12')
            ->setLabel('Order Items')
            ->setFormTypeOption('prototype', true)
            ->setFormTypeOption('prototype_name', '__order_item__')
            ->setFormTypeOption('allow_extra_fields', true)
            ->setFormTypeOption('entry_options', [
                'label' => false,
            ]);

        yield MoneyField::new('totalAmount')->setCurrency('USD')->hideOnForm();
        yield DateTimeField::new('createdAt', 'Order Date')->hideOnForm();

        yield TextareaField::new('shippingAddress')->hideOnIndex()->setColumns('col-md-6');
        yield TextareaField::new('billingAddress')->hideOnIndex()->setColumns('col-md-6');

        yield TextField::new('paymentMethod')->hideOnIndex()->setColumns('col-md-6');
        yield TextField::new('paymentStatus')->hideOnIndex()->setColumns('col-md-6');

        yield TextField::new('transactionId', 'Transaction ID')->hideOnIndex()->setColumns('col-md-6');
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
