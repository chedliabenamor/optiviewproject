<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use App\Service\UserManager;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use Symfony\Component\HttpFoundation\RequestStack;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;

class UserCrudController extends AbstractCrudController
{
    private Security $security;
    private UserManager $userManager;
    private RequestStack $requestStack;
    private UserRepository $userRepository;

    public function __construct(Security $security, UserManager $userManager, RequestStack $requestStack, UserRepository $userRepository)
    {
        $this->security = $security;
        $this->userManager = $userManager;
        $this->requestStack = $requestStack;
        $this->userRepository = $userRepository;
    }
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            $this->userManager->encodePassword($entityInstance);
        }
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            $this->userManager->encodePassword($entityInstance);
        }
        parent::updateEntity($entityManager, $entityInstance);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_DETAIL, fn(User $user) => sprintf('Customer ID #%s - %s %s', $user->getId(), $user->getName(), $user->getLastname()))
            ->setHelp(Crud::PAGE_DETAIL, 'This is a custom view of the user details.')
            ->overrideTemplates([
                'crud/detail' => 'admin/user/detail.html.twig',
                'crud/index' => 'admin/user/index.html.twig'
            ])
            ->setPaginatorPageSize(10) // Default rows per page
            ->setPaginatorRangeSize(4); // Number of page links to show (e.g., 1 2 3 4 ...)
    }

    public function configureFields(string $pageName): iterable
    {
        if (in_array($pageName, [Crud::PAGE_NEW, Crud::PAGE_EDIT])) {
            // For new and edit forms
            yield TextField::new('name')->setRequired(true)->setColumns('col-md-6');
            yield TextField::new('lastname')->setRequired(true)->setColumns('col-md-6');

            yield EmailField::new('email')->setRequired(true)->setColumns('col-md-6');

            // Add password field only on create and edit pages
            yield TextField::new('plainPassword', 'Password')
                ->setFormType(PasswordType::class)
                ->setRequired($pageName === Crud::PAGE_NEW)
                ->hideOnIndex()
                ->setHelp($pageName === Crud::PAGE_EDIT ? 'Leave empty to keep current password' : '')
                ->setColumns('col-md-6');

            yield TextField::new('phone')->setRequired(false)->setColumns('col-md-6');

            yield ChoiceField::new('sex')
                ->setChoices(['Male' => 'male', 'Female' => 'female', 'Other' => 'other'])
                ->setRequired(false)
                ->setColumns('col-md-6');

            yield DateField::new('birthdate')
                ->setRequired(false)
                ->setColumns('col-md-6');


            yield ChoiceField::new('roles')
                ->setChoices([
                    'Administrator' => 'ROLE_ADMIN',
                    'User' => 'ROLE_USER',
                ])
                ->allowMultipleChoices()
                ->renderExpanded()
                ->setRequired(true)
                ->setColumns('col-md-6');

            yield IntegerField::new('loyaltyPoints')->setRequired(false)->setColumns('col-md-6');
            yield TextareaField::new('address')->setRequired(false)->setColumns('col-md-12');

            if (Crud::PAGE_EDIT === $pageName) {
                $currentUser = $this->security->getUser();
                $contextUser = $this->getContext()->getEntity()->getInstance();

                if (
                    !$contextUser instanceof User ||
                    !$currentUser instanceof User ||
                    $contextUser->getId() !== $currentUser->getId()
                ) {
                    yield BooleanField::new('active')->setColumns('col-md-12');
                }
            } else {
                yield BooleanField::new('active')->setColumns('col-md-12');
            }
        }

        if (Crud::PAGE_INDEX === $pageName) {
            yield TextField::new('name', 'User')
                ->formatValue(function ($value, User $user) {
                    $fullName = $user->getName() . ' ' . $user->getLastname();
                    $email = $user->getEmail();
                    $avatarHtml = '<span class="fa-stack fa-lg me-2" style="vertical-align: middle;"><i class="fas fa-circle fa-stack-2x text-secondary"></i><i class="fas fa-user fa-stack-1x fa-inverse"></i></span>';
                    return sprintf(
                        '<div class="d-flex align-items-center">%s<div><strong>%s</strong><br><small class="text-muted">%s</small></div></div>',
                        $avatarHtml,
                        htmlspecialchars($fullName),
                        htmlspecialchars($email)
                    );
                })
                ->setSortable(true);

            yield ArrayField::new('roles', 'Role')
                ->formatValue(function ($value, User $user) {
                    $roles = $user->getRoles();
                    $roleIcons = [
                        'ROLE_ADMIN' => 'fas fa-user-shield text-danger',
                        'ROLE_USER' => 'fas fa-user text-muted',
                    ];
                    $primaryRole = 'ROLE_USER';
                    $priority = ['ROLE_ADMIN', 'ROLE_USER'];
                    foreach ($priority as $pRole) {
                        if (in_array($pRole, $roles)) {
                            $primaryRole = $pRole;
                            break;
                        }
                    }
                    $roleName = ucfirst(strtolower(str_replace('ROLE_', '', $primaryRole)));
                    $iconClass = $roleIcons[$primaryRole] ?? 'fas fa-user text-muted';
                    return sprintf('<i class="%s me-1"></i> %s', $iconClass, htmlspecialchars($roleName));
                })
                ->setSortable(false);

            yield BooleanField::new('active', 'Status')
                ->formatValue(function ($value) {
                    return $value
                        ? '<span class="badge text-white bg-success px-2 py-1">ACTIVE</span>'
                        : '<span class="badge text-white bg-danger px-2 py-1">INACTIVE</span>';
                });
        } elseif (Crud::PAGE_DETAIL === $pageName) {
            yield IdField::new('id');
            yield EmailField::new('email');
            yield TextField::new('name');
            yield TextField::new('lastname');
            yield ArrayField::new('roles');
            yield TextField::new('phone');
            yield TextareaField::new('address');
            yield DateField::new('birthdate');
            yield ChoiceField::new('sex')
                ->setChoices(['Male' => 'male', 'Female' => 'female', 'Other' => 'other']);
            yield IntegerField::new('loyaltyPoints');
            yield BooleanField::new('active');
            yield AssociationField::new('orders')->setLabel('Order History');
            yield AssociationField::new('cart')->setLabel('Shopping Cart');
            yield AssociationField::new('wishlist')->setLabel('Wishlist');
        } else { // For PAGE_NEW and PAGE_EDIT
            yield TextField::new('name')->setRequired(true)->setColumns('col-md-6');
            yield TextField::new('lastname')->setRequired(true)->setColumns('col-md-6');

            yield EmailField::new('email')->setRequired(true)->setColumns('col-md-6');
            yield TextField::new('phone')->setRequired(false)->setColumns('col-md-6');

            yield ChoiceField::new('sex')
                ->setChoices(['Male' => 'male', 'Female' => 'female', 'Other' => 'other'])
                ->setRequired(false)
                ->setColumns('col-md-6');
            yield DateField::new('birthdate')
                ->setRequired(false)
                ->setColumns('col-md-6');



            yield ChoiceField::new('roles')
                ->setChoices([
                    'Administrator' => 'ROLE_ADMIN',
                    'User' => 'ROLE_USER',
                ])
                ->allowMultipleChoices()
                ->renderExpanded()
                ->setRequired(true)
                ->setColumns('col-md-6');
            yield IntegerField::new('loyaltyPoints')->setRequired(false)->setColumns('col-md-6');

            yield TextareaField::new('address')->setRequired(false)->setColumns('col-md-12');

            if (Crud::PAGE_EDIT === $pageName) {
                $currentUser = $this->security->getUser();
                $contextUser = $this->getContext()->getEntity()->getInstance();

                if (
                    !$contextUser instanceof User ||
                    !$currentUser instanceof User ||
                    $contextUser->getId() !== $currentUser->getId()
                ) {
                    yield BooleanField::new('active')->setColumns('col-md-12');
                }
            } else {
                yield BooleanField::new('active')->setColumns('col-md-12');
            }
        }
    }

    public function configureActions(Actions $actions): Actions
    {
        $currentUser = $this->getUser();

        // Activate Action (shows for inactive users)
        $activateAction = Action::new('activateUser', 'Activate', 'fa fa-check-circle')
            ->setCssClass('btn btn-success')
            ->linkToCrudAction('activateUser')
            ->displayIf(fn(User $user) => !$user->isActive());

        // Deactivate Action (shows for active users, but not for the current user)
        $deactivateAction = Action::new('deactivateUser', 'Deactivate', 'fa fa-times-circle')
            ->setCssClass('btn btn-danger')
            ->linkToCrudAction('deactivateUser')
            ->displayIf(function (User $user) use ($currentUser) {
                return $user->isActive() && $user !== $currentUser;
            });

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $activateAction)
            ->add(Crud::PAGE_INDEX, $deactivateAction)
            ->update(Crud::PAGE_INDEX, Action::NEW, fn(Action $action) => $action->setIcon('fa fa-user-plus')->setLabel('Add User'))
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn(Action $action) => $action->setIcon('fa fa-edit')->setLabel('Edit'))
            ->update(Crud::PAGE_INDEX, Action::DETAIL, fn(Action $action) => $action->setIcon('fa fa-eye')->setLabel('Show'))
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, 'activateUser', 'deactivateUser']);
    }

    public function deactivateUser(AdminContext $context)
    {
        $user = $context->getEntity()->getInstance();
        if ($user instanceof User) {
            $this->userManager->deactivateUser($user);
            $this->addFlash('success', sprintf('User %s has been deactivated.', $user->getUserIdentifier()));
        } else {
            $this->addFlash('danger', 'Invalid user entity.');
        }

        $url = $context->getReferrer();
        if (null === $url) {
            $url = $this->container->get(AdminUrlGenerator::class)->setController(self::class)->setAction(Action::INDEX)->generateUrl();
        }

        return $this->redirect($url);
    }

    public function activateUser(AdminContext $context)
    {
        $user = $context->getEntity()->getInstance();
        if ($user instanceof User) {
            $this->userManager->activateUser($user);
            $this->addFlash('success', sprintf('User %s has been activated.', $user->getUserIdentifier()));
        } else {
            $this->addFlash('danger', 'Invalid user entity.');
        }

        $url = $context->getReferrer();
        if (null === $url) {
            $url = $this->container->get(AdminUrlGenerator::class)->setController(self::class)->setAction(Action::INDEX)->generateUrl();
        }

        return $this->redirect($url);
    }

public function configureFilters(Filters $filters): Filters
{
    return $filters
        ->add(ChoiceFilter::new('roles')
            ->setChoices([
                'Administrator' => 'ROLE_ADMIN',
                'User' => 'ROLE_USER',
            ])
            ->canSelectMultiple(true)
        )
        ->add(BooleanFilter::new('active', 'Status')
            ->setFormTypeOption('choices', [
                'Active' => true,
                'Inactive' => false
            ])
        );
}

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters
    ): QueryBuilder {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $role = $request->query->get('role');
            $active = $request->query->get('active');
            if ($role) {
                $qb->andWhere('entity.roles LIKE :role')->setParameter('role', '%"' . $role . '"%');
            }
            if ($active !== null && $active !== '') {
                $qb->andWhere('entity.active = :active')->setParameter('active', (bool) $active);
            }
        }
        return $qb;
    }

    public function index(AdminContext $context)
    {
        $stats = $this->userRepository->getUserStats();

        $response = parent::index($context);
        if ($response instanceof KeyValueStore) {
            $response->set('totalUsers', $stats['total']);
            $response->set('activeUsers', $stats['active']);
            $response->set('inactiveUsers', $stats['inactive']);
        }
        return $response;
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $stats = $this->userRepository->getUserStats();
        $responseParameters->set('totalUsers', $stats['total']);
        $responseParameters->set('activeUsers', $stats['active']);
        $responseParameters->set('inactiveUsers', $stats['inactive']);
        return $responseParameters;
    }
}
