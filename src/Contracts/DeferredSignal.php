<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Contracts;

/**
 * A slow or networked soft signal. It runs after every other soft signal, and
 * only when the score hasn't reached the threshold yet.
 */
interface DeferredSignal extends Signal {}
