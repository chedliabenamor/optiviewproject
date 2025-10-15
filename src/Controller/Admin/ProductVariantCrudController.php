<?php

namespace App\Controller\Admin;

use App\Entity\ProductVariant;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use Vich\UploaderBundle\Form\Type\VichFileType;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Controller\Admin\ProductVariantImageCrudController;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Service\SkuGeneratorService;
use EasyCorp\Bundle\EasyAdminBundle\Exception\ForbiddenActionException;
use EasyCorp\Bundle\EasyAdminBundle\Exception\InsufficientEntityPermissionException;

class ProductVariantCrudController extends AbstractCrudController
{
    private RequestStack $requestStack;
    private AdminUrlGenerator $adminUrlGenerator;
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;
    private SkuGeneratorService $skuGeneratorService;

    public function __construct(
        RequestStack $requestStack,
        AdminUrlGenerator $adminUrlGenerator,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        SkuGeneratorService $skuGeneratorService
    ) {
        $this->requestStack = $requestStack;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->skuGeneratorService = $skuGeneratorService;
    }

    public static function getEntityFqcn(): string
    {
        return ProductVariant::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('detail', fn(ProductVariant $variant) => sprintf('Variant: %s', $variant->getSku() ?: $variant->getColor() ?: ('#' . $variant->getId())))
            ->setPaginatorPageSize(10)
            ->setPaginatorRangeSize(4)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        // Only show product field when editing existing variants or creating standalone variants
        if ($pageName === Crud::PAGE_EDIT || ($pageName === Crud::PAGE_NEW && $this->isStandaloneForm())) {
            yield AssociationField::new('product')->autocomplete()->setColumns('col-md-12');
        }
        
        yield AssociationField::new('color')
            ->setColumns('col-md-4')
            ->setFormTypeOption('attr', ['data-ea-autocomplete-placeholder' => 'e.g. Black', 'placeholder' => 'e.g. Black'])
            ->setFormTypeOption('help_html', true)
            ->setHelp('<button type="button" class="btn btn-primary btn-sm mt-2 ea-quick-add" data-quick-add="color" data-fetch="/admin-ajax/color/new" data-title="Add Color"><i class="fa fa-plus me-1"></i> Add Color</button>');

        yield AssociationField::new('style')
            ->autocomplete()
            ->setColumns('col-md-4')
            ->setFormTypeOption('attr', ['data-ea-autocomplete-placeholder' => 'e.g. Retro', 'placeholder' => 'e.g. Retro'])
            ->setFormTypeOption('help_html', true)
            ->setHelp('<button type="button" class="btn btn-primary btn-sm mt-2 ea-quick-add" data-quick-add="style" data-fetch="/admin-ajax/style/new" data-title="Add Style"><i class="fa fa-plus me-1"></i> Add Style</button>');

        yield AssociationField::new('genre')
            ->autocomplete()
            ->setColumns('col-md-4')
            ->setFormTypeOption('attr', ['data-ea-autocomplete-placeholder' => 'e.g. Unisex', 'placeholder' => 'e.g. Unisex'])
            ->setFormTypeOption('help_html', true)
            ->setHelp('<button type="button" class="btn btn-primary btn-sm mt-2 ea-quick-add" data-quick-add="genre" data-fetch="/admin-ajax/genre/new" data-title="Add Genre"><i class="fa fa-plus me-1"></i> Add Genre</button>');

        yield TextField::new('sku')->setColumns('col-md-4')->setHelp('SKU will be auto-generated if left empty');
        yield MoneyField::new('price')->setCurrency('EUR')->setStoredAsCents(false)->setColumns('col-md-6')->setRequired(true);

        yield NumberField::new('stock')->setColumns('col-md-6')->setRequired(true);
        
        // Overlay file upload for this variant (3D or PNG)
        yield Field::new('overlayFile', 'Overlay File (3D or PNG)')
            ->setFormType(VichFileType::class)
            ->setFormTypeOptions([
                'allow_delete' => true,
                'download_uri' => true,
                'required' => false,
                'attr' => [
                    'accept' => '.png,.webp,.jpg,.jpeg,.gltf,.glb,.obj'
                ],
            ])
            ->onlyOnForms()
            ->setHelp('Allowed: .png, .webp, .jpg, .jpeg, .glb, .gltf, .obj (PNG recommended for 2D overlay)');
        yield TextField::new('overlayAsset', 'Overlay Asset')->onlyOnIndex();

        yield CollectionField::new('productVariantImages')
            ->useEntryCrudForm(ProductVariantImageCrudController::class)
            ->setFormTypeOptions([
                'by_reference' => false,
            ])
            ->onlyOnForms()
            ->setColumns('col-md-12');
    }

        public function configureActions(Actions $actions): Actions
    {
        $archiveVariantAction = Action::new('archiveVariant', 'Archive', 'fa fa-archive')
            ->setCssClass('btn btn-warning btn-sm text-white')
            ->linkToCrudAction('archiveVariant');

        $restoreVariantAction = Action::new('restoreVariant', 'Restore', 'fa fa-undo')
            ->setCssClass('btn btn-success btn-sm text-white')
            ->linkToCrudAction('restoreVariant');

        $request = $this->requestStack->getCurrentRequest();
        $showArchived = $request?->query->get('show') === 'archived';

        $toggleArchivedAction = Action::new(
            $showArchived ? 'viewActive' : 'viewArchived',
            $showArchived ? 'View Active' : 'View Archived'
        )
            ->linkToUrl(
                $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Crud::PAGE_INDEX)
                    ->set('show', $showArchived ? null : 'archived')
                    ->generateUrl()
            )
            ->createAsGlobalAction()
            ->addCssClass('btn btn-secondary');

        if ($showArchived) {
            $archiveOrRestoreAction = Action::new('restore', 'Restore')
                ->setIcon('fa fa-undo')
                ->setCssClass('btn btn-success btn-sm text-white')
                ->linkToCrudAction('restoreVariant');
            $archiveOrRestoreActionName = 'restore';
        } else {
            $archiveOrRestoreAction = Action::new('archive', 'Archive')
                ->setIcon('fa fa-archive')
                ->setCssClass('btn btn-warning btn-sm text-white')
                ->linkToCrudAction('archiveVariant');
            $archiveOrRestoreActionName = 'archive';
        }

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, fn(Action $action) =>
                $action->setIcon('fa fa-eye')->setLabel('Show'))
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn(Action $action) =>
                $action->setIcon('fa fa-edit')->setLabel('Edit'))
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_DETAIL, Action::DELETE)
            ->add(Crud::PAGE_INDEX, $archiveOrRestoreAction)
            ->add(Crud::PAGE_INDEX, $toggleArchivedAction)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, $archiveOrRestoreActionName])
            
            ->add(Crud::PAGE_DETAIL, $restoreVariantAction)
            ->add(Crud::PAGE_DETAIL, $archiveVariantAction);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): \Doctrine\ORM\QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        $showArchived = $this->requestStack->getCurrentRequest()?->query->get('show') === 'archived';

        if ($showArchived) {
            $queryBuilder->andWhere('entity.deletedAt IS NOT NULL');
        } else {
            $queryBuilder->andWhere('entity.deletedAt IS NULL');
        }

        return $queryBuilder;
    }

        public function archiveVariant(AdminContext $context): Response
    {
        $variant = $context->getEntity()->getInstance();
        if ($variant instanceof ProductVariant) {
            $variant->setDeletedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Variant "%s" was archived.', $variant->getSku()));
        }

        $url = $this->adminUrlGenerator
            ->setController(ProductCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($variant->getProduct()->getId())
            ->generateUrl();

        return $this->redirect($url);
    }

        public function restoreVariant(AdminContext $context): Response
    {
        $variant = $context->getEntity()->getInstance();
        if ($variant instanceof ProductVariant) {
            $variant->setDeletedAt(null);
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Variant "%s" was restored.', $variant->getSku()));
        }

        $url = $this->adminUrlGenerator
            ->setController(ProductCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($variant->getProduct()->getId())
            ->generateUrl();

        return $this->redirect($url);
    }

    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        $variant = $context->getEntity()->getInstance();
        if ($variant instanceof ProductVariant && $variant->getProduct()) {
            $url = $this->adminUrlGenerator
                ->setController(ProductCrudController::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($variant->getProduct()->getId())
                ->generateUrl();

            return $this->redirect($url);
        }

        return parent::getRedirectResponseAfterSave($context, $action);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        try {
            if ($entityInstance instanceof ProductVariant) {
                // Validate before any persistence to show clear errors (e.g., price required)
                $violations = $this->validator->validate($entityInstance);
                if (count($violations) > 0) {
                    foreach ($violations as $violation) {
                        $this->addFlash('error', $violation->getMessage());
                    }
                    throw new \RuntimeException('Validation failed');
                }

                // Auto-generate SKU if not provided (no pre-flush needed)
                if (empty($entityInstance->getSku())) {
                    if (!$entityInstance->getProduct()) {
                        $this->addFlash('error', 'Product must be selected before generating SKU.');
                        throw new \RuntimeException('Product required for SKU generation');
                    }
                    $sku = $this->skuGeneratorService->generateProductVariantSku($entityInstance);
                    $entityInstance->setSku($sku);
                }
            }

            parent::persistEntity($entityManager, $entityInstance);
            $this->addFlash('success', 'Product variant saved successfully!');
            
        } catch (UniqueConstraintViolationException $e) {
            // Fallback for database-level constraint violations
            if (strpos($e->getMessage(), 'UNIQ_') !== false && strpos($e->getMessage(), 'sku') !== false) {
                $this->addFlash('error', 'The SKU "' . $entityInstance->getSku() . '" is already in use. Please choose a different SKU.');
            } else {
                $this->addFlash('error', 'A database constraint error occurred. Please check your data and try again.');
            }
            // Don't re-throw the exception to prevent the error page
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'Validation failed') {
                // Don't add additional error message, validation errors already added
                return;
            }
            throw $e;
        } catch (\Exception $e) {
            $this->addFlash('error', 'An error occurred while saving the product variant. Please try again.');
        }
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        try {
            if ($entityInstance instanceof ProductVariant) {
                // Auto-generate SKU if empty
                if (empty($entityInstance->getSku())) {
                    // Check if product is set
                    if (!$entityInstance->getProduct()) {
                        $this->addFlash('error', 'Product must be selected before generating SKU.');
                        throw new \RuntimeException('Product required for SKU generation');
                    }
                    
                    $sku = $this->skuGeneratorService->generateProductVariantSku($entityInstance);
                    $entityInstance->setSku($sku);
                    $this->addFlash('info', 'SKU auto-generated: ' . $sku);
                }
            }
            
            // Validate the entity before updating
            $violations = $this->validator->validate($entityInstance);
            
            if (count($violations) > 0) {
                foreach ($violations as $violation) {
                    $this->addFlash('error', $violation->getMessage());
                }
                // Don't proceed with update if there are validation errors
                throw new \RuntimeException('Validation failed');
            }

            parent::updateEntity($entityManager, $entityInstance);
            $this->addFlash('success', 'Product variant updated successfully!');
            
        } catch (UniqueConstraintViolationException $e) {
            // Fallback for database-level constraint violations
            if (strpos($e->getMessage(), 'UNIQ_') !== false && strpos($e->getMessage(), 'sku') !== false) {
                $this->addFlash('error', 'The SKU "' . $entityInstance->getSku() . '" is already in use. Please choose a different SKU.');
            } else {
                $this->addFlash('error', 'A database constraint error occurred. Please check your data and try again.');
            }
            // Don't re-throw the exception to prevent the error page
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'Validation failed') {
                // Don't add additional error message, validation errors already added
                return;
            }
            throw $e;
            $this->addFlash('error', 'An error occurred while updating the product variant. Please try again.');
        }
    }

    

    /**
     * Check if we're creating a standalone variant (not within a product form)
     */
    private function isStandaloneForm(): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        
        // Check if we're accessing the variant controller directly (not via collection)
        return $request && str_contains($request->getPathInfo(), '/admin/product-variant/new');
    }
}
