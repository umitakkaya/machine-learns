<?php

namespace AppBundle\Command;

use Phpml\Classification\Classifier;
use Phpml\Classification\SVC;
use Phpml\CrossValidation\StratifiedRandomSplit;
use Phpml\Dataset\ArrayDataset;
use Phpml\Dataset\CsvDataset;
use Phpml\Dataset\Dataset;
use Phpml\FeatureExtraction\TfIdfTransformer;
use Phpml\FeatureExtraction\TokenCountVectorizer;
use Phpml\Metric\Accuracy;
use Phpml\SupportVectorMachine\Kernel;
use Phpml\Tokenization\WordTokenizer;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class LanguageDetectionCommand extends ContainerAwareCommand
{

	const TEST_SIZE = 0.1;
	const SVC_COST  = 100000;

	const LANGUAGES = [
		'english',
		'french',
		'italian',
		'turkish',
		'polish',
	];

	/** @var TokenCountVectorizer */
	private $vectorizer;

	/** @var TfIdfTransformer */
	private $transformer;

	/**
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		$this
			->setName('app:language-detection')
			->setDescription('Hello PhpStorm');
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$io          = new SymfonyStyle($input, $output);
		$container   = $this->getContainer();
		$datasetPath = $container->get('file_locator')->locate('@AppBundle/Resources/Dataset/languages.csv');

		$dataset = new CsvDataset($datasetPath, 1);

		$randomSplit = $this->transformInput($dataset);
		$classifier  = $this->train($randomSplit);

		//$text     = $io->ask('Write a text:');
		//$language = $io->choice('In which language it was:', self::LANGUAGES);


		$testSamples     = $randomSplit->getTestSamples();
		$predictedLabels = $classifier->predict($testSamples);
		$score           = Accuracy::score($randomSplit->getTestLabels(), $predictedLabels);

		$output->writeln(sprintf('Accuracy: %3.2f', $score));
	}




	private function transformInput(Dataset $dataset): StratifiedRandomSplit
	{
		$samples          = array_column($dataset->getSamples(), 0);
		$vectorizer       = $this->getVectorizer();
		$tfIdfTransformer = $this->getTransformer();

		$vectorizer->fit($samples);
		$vectorizer->transform($samples);

		$tfIdfTransformer->fit($samples);
		$tfIdfTransformer->transform($samples);

		$dataset     = new ArrayDataset($samples, $dataset->getTargets());
		$randomSplit = new StratifiedRandomSplit($dataset, self::TEST_SIZE);

		return $randomSplit;
	}

	private function train(StratifiedRandomSplit $randomSplit): Classifier
	{
		$classifier = new SVC(Kernel::RBF, self::SVC_COST);

		$classifier->train($randomSplit->getTrainSamples(), $randomSplit->getTrainLabels());

		return $classifier;
	}

	private function getTransformer()
	{
		if (null === $this->transformer)
		{
			$this->transformer = new TfIdfTransformer();
		}

		return $this->transformer;

	}

	private function getVectorizer(): TokenCountVectorizer
	{
		if (null === $this->vectorizer)
		{
			$this->vectorizer = new TokenCountVectorizer(new WordTokenizer());
		}

		return $this->vectorizer;
	}

	private function predictSingle(Classifier $classifier, string $text, string $language, OutputInterface $output)
	{
		$dataset     = new ArrayDataset([[$text]], [$language]);
		$randomSplit = $this->transformInput($dataset);

		$testSamples     = $randomSplit->getTestSamples();
		$predictedLabels = $classifier->predict($testSamples);

		$output->writeln(sprintf('%s was predicted as: %s', $text, reset($predictedLabels)));
	}
}
