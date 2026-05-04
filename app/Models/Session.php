<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Session extends Model
{
    protected $table = 'claude_sessions';

    protected $fillable = [
        'guid',
        'account_id',
        'workspace_id',
        'label',
        'first_user_prompt',
        'status',
        'jsonl_path',
        'discovered_cwd',
        'jsonl_size_bytes',
        'jsonl_mtime',
        'registered',
        'dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'jsonl_mtime' => 'datetime',
            'dismissed_at' => 'datetime',
            'registered' => 'boolean',
            'jsonl_size_bytes' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
