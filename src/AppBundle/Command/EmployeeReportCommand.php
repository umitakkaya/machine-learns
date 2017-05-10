<?php

namespace AppBundle\Command;

use Phpml\Classification\KNearestNeighbors;
use Phpml\Classification\SVC;
use Phpml\CrossValidation\StratifiedRandomSplit;
use Phpml\Dataset\CsvDataset;
use Phpml\Math\Distance\Minkowski;
use Phpml\Metric\Accuracy;
use Phpml\SupportVectorMachine\Kernel as MLKernel;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class EmployeeReportCommand extends ContainerAwareCommand
{
	const NUM_FEATURING_COLUMN = 7;
	const TEST_SIZE_PERCENTAGE = 1;
	const TEST_SIZE_DIVIDE_BY  = 100;
	const SEED                 = 50;
	const NEAREST_NEIGHBORS    = 3;
	const SVC_COST             = 10000;

	/**
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		$this
			->setName('app:employee:report')
			->addOption('use-k-near', null, InputOption::VALUE_NONE, 'Force application to use KNear algorithm')
			->addOption('test-accuracy', 'a', InputOption::VALUE_NONE, 'Show accuracy based on test samples');
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$container   = $this->getContainer();
		$datasetPath = $container->get('file_locator')->locate('@AppBundle/Resources/Dataset/employee.csv');

		$io          = new SymfonyStyle($input, $output);
		$dataset     = new CsvDataset($datasetPath, self::NUM_FEATURING_COLUMN);
		$randomSplit = new StratifiedRandomSplit($dataset, self::TEST_SIZE_PERCENTAGE / self::TEST_SIZE_DIVIDE_BY);
		$classifier  = new SVC(MLKernel::RBF, self::SVC_COST);

		if (true === $input->getOption('use-k-near'))
		{
			$classifier = new KNearestNeighbors(self::NEAREST_NEIGHBORS, new Minkowski);
		}


		$io->write('Training...');
		$classifier->train($randomSplit->getTrainSamples(), $randomSplit->getTrainLabels());
		$io->writeln('Done!');

		if ($input->getOption('test-accuracy'))
		{
			$predictedLabels = $classifier->predict($randomSplit->getTestSamples());
			$actualLabels    = $randomSplit->getTestLabels();

			$results = $this->combineValues($randomSplit->getTestSamples(), $actualLabels);
			$results = $this->combineValues($results, $predictedLabels);

			$this->writeResults($results, $io, $output);

			$output->writeln('Accuracy:');
			$output->writeln(Accuracy::score($randomSplit->getTestLabels(), $predictedLabels));

			return 0;
		}


		$samples = [];
		$labels  = [];

		do
		{
			$io->$samples[] =
				[
					$io->ask('Satisfaction'),
					$io->ask('Evaluation'),
					$io->ask('Number of project'),
					$io->ask('Average hours'),
					$io->ask('Years spent'),
					$io->choice('Work accident', [0, 1], 0),
					$io->choice('Promotion', [0, 1], 0),
				];

			$labels[] = $io->confirm('Still employed?') ? 0 : 1;
		} while ($io->confirm('Would you like to test another sample?'));

		$predicted = $classifier->predict($samples);

		$userResults = $this->combineValues($samples, $labels);
		$userResults = $this->combineValues($userResults, $predicted);

		$this->writeResults($userResults, $io, $output);

		return 0;
	}

	private function writeResults(array $results, SymfonyStyle $io, OutputInterface $output)
	{


		$output->writeln('Samples:');
		$io->table(
			[
				'satisfaction_level',
				'last_evaluation',
				'number_project',
				'average_monthly_hours',
				'time_spend_company',
				'Work_accident',
				'promotion_last_5years',
				'left',
				'prediction',
			], $results);
	}

	private function combineValues(array $a, array $b): array
	{
		$finalArray = [];

		foreach ($a as $key => $value)
		{
			$finalArray[] = array_merge($value, [$b[$key]]);
		}

		return $finalArray;
	}
}
