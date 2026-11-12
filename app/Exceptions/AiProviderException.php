<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A provider call failed. Messages are short and safe to store ("HTTP 503", "Invalid JSON"): never a prompt or response body.
 */
class AiProviderException extends RuntimeException {}
