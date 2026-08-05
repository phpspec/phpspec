<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 * (c) Ciaran McNulty <ciaran@ciaranmcnulty.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec;

use Closure;
use PhpSpec\EventDispatcher\Event\AttachmentCreated;
use PhpSpec\EventDispatcher\Subscriber;

/**
 * @internal
 * What a spec or a scenario handed over about itself, kept until the moment it
 * is worth reading. PhpSpec cannot see the log a subject wrote or the output of
 * a process a step drove, so the test says so itself; and because the value is
 * often still being written when it is offered, a closure is read at the end
 * rather than when it was handed over.
 *
 * Nothing is read for a unit that passed: an attachment is a diagnosis, and a
 * green run has nothing to diagnose.
 */
final class Attachments implements Subscriber
{
    /** @var array<string, string|Closure> what was handed over, by name; the latest under a name wins */
    private array $offered = [];

    /**
     * Returns the event names this subscriber listens to.
     *
     * @return array<string, string> map of event name to handler method name
     */
    public function getSubscribedEvents(): array
    {
        return [
            AttachmentCreated::NAME => 'onAttachmentCreated',
        ];
    }

    /**
     * Takes one attachment. A name handed over twice keeps the latest: a helper
     * offering the same log on every poll must not pile up copies of it.
     *
     * @param string|Closure $value the text, or a closure read when the failure is reported
     */
    public function add(string $name, string|Closure $value): void
    {
        $this->offered[$name] = $value;
    }

    /**
     * Takes one that arrived over the event bus, which is how a step or an
     * example reaches the collector for the unit it is running in.
     */
    public function onAttachmentCreated(AttachmentCreated $event): void
    {
        $this->add($event->name, $event->value);
    }

    /**
     * Reads everything handed over, now. Called while the run is still standing
     * where the attachment was made, before any teardown removes what it points
     * at, so a closure reading a file still finds the file.
     *
     * A closure that fails says so instead of leaving an empty block, which
     * would read as "the subject printed nothing" rather than "PhpSpec could
     * not look".
     *
     * @return array<string, string|array{error: string}>
     */
    public function read(): array
    {
        $read = [];

        foreach ($this->offered as $name => $value) {
            $read[$name] = $value instanceof Closure ? self::resolve($value) : $value;
        }

        return $read;
    }

    /**
     * Whether anything was handed over at all.
     */
    public function isEmpty(): bool
    {
        return $this->offered === [];
    }

    /**
     * @return string|array{error: string}
     */
    private static function resolve(Closure $value): string|array
    {
        try {
            $read = $value();
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }

        // What file_get_contents answers when it cannot read: reported as the
        // failure to look that it is, never as an empty attachment.
        if ($read === false || $read === null) {
            return ['error' => 'Nothing could be read'];
        }

        return is_string($read) ? $read : (string) json_encode($read);
    }
}
