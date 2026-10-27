<?php

namespace App\Support;

use RuntimeException;

/** GD could not decode an upload that passed the header checks (e.g. truncated). Storage failures stay plain RuntimeExceptions. */
class GambarTidakTerbaca extends RuntimeException {}
