<?php

namespace App\DTOs\Players;

use Illuminate\Http\Request;

readonly class PlayerFilterDTO
{
    public function __construct(
        public ?string $search = null,
        public ?string $status = null,
        public ?string $state = null,
        public ?string $city = null,
        public ?string $affiliate = null,
        public ?int $tagId = null,
        public ?string $createdFrom = null,
        public ?string $createdTo = null,
        public ?string $lastLoginFrom = null,
        public ?string $lastLoginTo = null,
        public string $sort = 'created_at',
        public string $direction = 'desc',
        public int $perPage = 20,
        public ?string $cursor = null,
        public bool $useCursor = false
    ) {}

    public static function fromRequest(Request $request): self
    {
        $allowedSorts = ['id', 'name', 'created_at', 'last_login_at', 'status', 'email'];
        $sort = in_array($request->query('sort'), $allowedSorts, true)
            ? $request->query('sort')
            : 'created_at';

        $direction = strtolower($request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        return new self(
            search: $request->query('search'),
            status: $request->query('status'),
            state: $request->query('state'),
            city: $request->query('city'),
            affiliate: $request->query('affiliate'),
            tagId: $request->query('tag_id') ? (int) $request->query('tag_id') : null,
            createdFrom: $request->query('created_from'),
            createdTo: $request->query('created_to'),
            lastLoginFrom: $request->query('last_login_from'),
            lastLoginTo: $request->query('last_login_to'),
            sort: $sort,
            direction: $direction,
            perPage: $perPage,
            cursor: $request->query('cursor'),
            useCursor: $request->boolean('cursor_mode') || $request->has('cursor')
        );
    }
}
