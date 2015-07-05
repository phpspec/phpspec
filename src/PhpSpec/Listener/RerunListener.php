<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Listener;

use PhpSpec\Event\SuiteEvent;
use PhpSpec\Process\Prerequisites\SuitePrerequisitesInterface;
use PhpSpec\Process\ReRunner;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RerunListener implements EventSubscriberInterface
{
    /**
     * @var ReRunner
     */
    private $reRunner;

    /**
     * @var SuitePrerequisitesInterface
     */
    private $suitePrerequisites;

    /**
     * @param ReRunner $reRunner
     * @param SuitePrerequisitesInterface $suitePrerequisites
     */
    public function __construct(ReRunner $reRunner, SuitePrerequisitesInterface $suitePrerequisites)
    {
        $this->reRunner = $reRunner;
        $this->suitePrerequisites = $suitePrerequisites;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            'beforeSuite' => array('beforeSuite', 1000),
            'afterSuite' => array('afterSuite', -1000)
        );
    }

    /**
     * @param SuiteEvent $suiteEvent
     */
    public function beforeSuite(SuiteEvent $suiteEvent)
    {
        $this->suitePrerequisites->guardPrerequisites();
    }

    /**
     * @param SuiteEvent $suiteEvent
     */
    public function afterSuite(SuiteEvent $suiteEvent)
    {
        if ($suiteEvent->isWorthRerunning()) {
            $this->reRunner->reRunSuite();
        }
    }
}
