<?php

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Helper\DescriptorHelper;

// Define arguments and options
$definition = new InputDefinition([
  new InputArgument('name', InputArgument::REQUIRED, 'Your name.'),
  new InputOption('greeting', 'g', InputOption::VALUE_OPTIONAL, 'Custom greeting', 'Hello')
]);

// Parse the command line input
$input = new ArgvInput(null, $definition);

$name = $input->getArgument('name');
$greeting = $input->getOption('greeting');

echo "$greeting, $name!";

$output = new ConsoleOutput();
$descriptor = new DescriptorHelper();
// Output help information
$descriptor->describe($output, $definition);

