<?php

namespace App\Meetings;

use RuntimeException;

/** An API key is not configured; retrying the job will not help. */
class MissingCredentials extends RuntimeException {}
