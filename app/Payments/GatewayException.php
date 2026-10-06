<?php

namespace App\Payments;

use RuntimeException;

/** Gateway menolak / gagal dihubungi. Pesan untuk log & admin, bukan untuk pembeli. */
class GatewayException extends RuntimeException {}
