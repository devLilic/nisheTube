<?php

namespace App\Domain\YouTube\Contracts;

use App\Domain\YouTube\Data\CommentThreadsPage;
use App\Domain\YouTube\Data\CommentThreadsRequest;

interface CommentProvider
{
    public function listCommentThreads(CommentThreadsRequest $request): CommentThreadsPage;
}
