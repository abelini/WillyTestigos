<?php

declare(strict_types=1);

namespace Willy\Domain;

enum DispatchMode: string
{
    case Background = 'background';
    case Inline = 'inline';
}
