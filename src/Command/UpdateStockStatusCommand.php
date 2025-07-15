<?php
namespace App\Command;

use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:update-stock-status',
    description: 'Recalculates and persists stockStatus for all products',
)]
class UpdateStockStatusCommand extends Command
{
    public function __construct(
        private ProductRepository $productRepository,
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $products = $this->productRepository->findAll();
        $count = 0;

        foreach ($products as $product) {
            $product->updateStockStatus(); // triggers recalculation
            $this->em->persist($product);
            $count++;
        }

        $this->em->flush();

        $output->writeln("<info>✅ Updated $count products with correct stockStatus.</info>");
        return Command::SUCCESS;
    }
}
