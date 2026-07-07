<?php

namespace App;

enum GeneratedPostStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Archived = 'archived';
}
