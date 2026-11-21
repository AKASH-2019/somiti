<?php

namespace App\Services\Collection;

use DomainException;

/** A collection was refused for a business reason; the message is shown to the user in Bengali. */
class CollectionException extends DomainException {}
