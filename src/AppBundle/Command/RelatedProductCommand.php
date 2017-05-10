<?php

namespace AppBundle\Command;

use Phpml\Association\Apriori;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RelatedProductCommand extends ContainerAwareCommand
{
	/**
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		$this
			->setName('app:product:related')
			->addOption('support', 's', InputOption::VALUE_OPTIONAL, 'Apriori support', 5)
			->addOption('confidence', 'c', InputOption::VALUE_OPTIONAL, 'Apriori confidence', 25)
			->addOption('item', 'i', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'For which items we will find related ones');
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		/**
		 * @var float $support    - confidence, minimum relative amount of frequent item set in train sample
		 * @var float $confidence - confidence, minimum relative amount of item set in frequent item sets
		 */
		$items      = $input->getOption('item');
		$support    = $input->getOption('support') / 100;
		$confidence = $input->getOption('confidence') / 100;


		$associate = new Apriori($support, $confidence);

		$associate->train($this->getProductBaskets(), $labels = []);

		$predictions = $associate->predict($items);

		foreach ($predictions as $prediction)
		{
			$output->writeln(implode(', ', $prediction));
		}
	}

	private function getProductBaskets(): array
	{
		return [
			['beer', 'chips', 'nuts', 'water'],
			['whiskey', 'chocolate', 'grapes', 'coke'],
			['whiskey', 'coke'],
			['whiskey', 'grapes'],
			['whiskey', 'chocolate'],
			['vodka', 'coke', 'apple_juice'],
			['vodka', 'orange_juice', 'apple_juice', 'tequila', 'lemon', 'salt'],
			['vodka', 'orange_juice', 'apple_juice'],
			['gin', 'tonic', 'coke', 'lemon', 'sugar'],
			['vodka', 'gin', 'coke', 'tonic', 'sugar'],
			['rum', 'lemon', 'mint'],
			['vodka', 'lemon', 'mint'],
			['coffee', 'liquor', 'milk'],
			['beer', 'popcorn', 'nuts', 'water', 'chips'],
			['beer', 'chips', 'cigarette'],
			['tequila', 'lemon', 'beer', 'vodka', 'coke', 'chips', 'cigarette', 'salt', 'nuts'],
			['wine', 'parmesan', 'water'],
			['prosecco', 'orange', 'soda', 'lemon'],
			['prosecco', 'wine', 'parmesan', 'orange', 'soda'],
			['tequila', 'orange', 'orange_juice', 'grenadine_syrup', 'lemon'],
			['vodka', 'tequila', 'strawberry', 'grenadine_syrup', 'orange', 'lemon'],
			['vodka', 'tequila', 'strawberry', 'orange', 'sugar', 'lemon'],
			['vodka', 'tequila', 'orange_juice', 'lemon', 'salt'],
			['tequila', 'salt', 'lemon'],
		];
	}
}
