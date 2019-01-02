<?php

/**
 * This file is part of the PrestaSitemapBundle package.
 *
 * (c) PrestaConcept <www.prestaconcept.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Presta\SitemapBundle\Command;

use Presta\SitemapBundle\Service\DumperInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Command to dump the sitemaps to provided directory
 *
 * @author Konstantin Tjuterev <kostik.lv@gmail.com>
 */
class DumpSitemapsCommand extends ContainerAwareCommand
{
    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('presta:sitemaps:dump')
            ->setDescription('Dumps sitemaps to given location')
            ->addOption(
                'section',
                null,
                InputOption::VALUE_REQUIRED,
                'Name of sitemap section to dump, all sections are dumped by default'
            )
            ->addOption(
                'base-url',
                null,
                InputOption::VALUE_REQUIRED,
                'Base url to use for absolute urls. Good example - http://acme.com/, bad example - acme.com. Defaults to router.request_context.host parameter'
            )
            ->addOption(
                'gzip',
                null,
                InputOption::VALUE_NONE,
                'Gzip sitemap'
            )
            ->addArgument(
                'target',
                InputArgument::OPTIONAL,
                'Location where to dump sitemaps. Generated urls will not be related to this folder.',
                version_compare(Kernel::VERSION, '4.0') >= 0 ? 'public' : 'web'
            );
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $targetDir = rtrim($input->getArgument('target'), '/');

        $container = $this->getContainer();
        $dumper = $container->get('presta_sitemap.dumper');
        /* @var $dumper DumperInterface */

        if ($baseUrl = $input->getOption('base-url')) {
            $baseUrl = rtrim($baseUrl, '/') . '/';

            //sanity check
            if (!parse_url($baseUrl, PHP_URL_HOST)) {
                throw new \InvalidArgumentException(
                    'Invalid base url. Use fully qualified base url, e.g. http://acme.com/',
                    -1
                );
            }

            // Set Router's host used for generating URLs from configuration param
            // There is no other way to manage domain in CLI
            $request = Request::create($baseUrl);
            $container->set('request', $request);
            $container->get('router')->getContext()->fromRequest($request);
        } else {
            $baseUrl = $this->getBaseUrl();
        }

        if ($input->getOption('section')) {
            $output->writeln(
                sprintf(
                    "Dumping sitemaps section <comment>%s</comment> into <comment>%s</comment> directory",
                    $input->getOption('section'),
                    $targetDir
                )
            );
        } else {
            $output->writeln(
                sprintf(
                    "Dumping <comment>all sections</comment> of sitemaps into <comment>%s</comment> directory",
                    $targetDir
                )
            );
        }
        $options = array(
            'gzip' => (Boolean)$input->getOption('gzip'),
        );
        $filenames = $dumper->dump($targetDir, $baseUrl, $input->getOption('section'), $options);

        if ($filenames === false) {
            $output->writeln("<error>No URLs were added to sitemap by EventListeners</error> - this may happen when provided section is invalid");

            return;
        }

        $output->writeln("<info>Created/Updated the following sitemap files:</info>");
        foreach ($filenames as $filename) {
            $output->writeln("    <comment>$filename</comment>");
        }
    }

    /**
     * @return string
     */
    private function getBaseUrl()
    {
        $context = $this->getContainer()->get('router')->getContext();

        if ('' === $host = $context->getHost()) {
            throw new \RuntimeException(
                'Router host must be configured to be able to dump the sitemap, please see documentation.'
            );
        }

        $scheme = $context->getScheme();
        $port = '';

        if ('http' === $scheme && 80 != $context->getHttpPort()) {
            $port = ':'.$context->getHttpPort();
        } elseif ('https' === $scheme && 443 != $context->getHttpsPort()) {
            $port = ':'.$context->getHttpsPort();
        }

        return rtrim($scheme . '://' . $host . $port, '/') . '/';
    }
}
