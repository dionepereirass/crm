<?php

namespace App\Models;

use App\Enums\AutomationNodeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationNode extends Model
{
    use HasFactory;

    protected $table = 'automation_nodes';

    protected $fillable = [
        'automation_id',
        'node_key',
        'node_type',
        'name',
        'configuration',
        'position_x',
        'position_y',
    ];

    protected $casts = [
        'node_type' => AutomationNodeType::class,
        'configuration' => 'array',
        'position_x' => 'float',
        'position_y' => 'float',
    ];

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    public function outgoingEdges(): HasMany
    {
        return $this->hasMany(AutomationEdge::class, 'source_node_id');
    }

    public function incomingEdges(): HasMany
    {
        return $this->hasMany(AutomationEdge::class, 'target_node_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AutomationStep::class, 'node_id');
    }
}
