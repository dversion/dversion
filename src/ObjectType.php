<?php

declare(strict_types=1);

namespace Dversion;

enum ObjectType
{
    case TABLE;
    case VIEW;
    case TRIGGER;
    case PROCEDURE;
    case FUNCTION;
}
