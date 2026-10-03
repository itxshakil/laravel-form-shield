<?php

declare(strict_types=1);

namespace Itxshakil\FormShield\Contracts;

/**
 * A signal unambiguous enough to quarantine on its own, such as a filled
 * honeypot. Hard signals run before the known-sender exemption.
 */
interface HardSignal extends Signal {}
