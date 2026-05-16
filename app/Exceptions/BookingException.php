<?php

namespace App\Exceptions;

use RuntimeException;

class BookingConflictException extends RuntimeException {}
class BookingNotFoundException extends RuntimeException {}
class InvalidStatusTransitionException extends RuntimeException {}
