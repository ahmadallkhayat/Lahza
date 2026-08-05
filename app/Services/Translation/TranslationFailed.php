<?php

namespace App\Services\Translation;

use RuntimeException;

/** The provider was reachable but could not produce a translation. */
class TranslationFailed extends RuntimeException {}
