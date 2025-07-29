<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    use HasFactory;

    protected $table = 'feedback';

    protected $fillable = [
        'user_id',
        'type',
        'category',
        'subject',
        'description',
        'priority',
        'contact_email',
    ];

    protected $casts = [
        //
    ];

    // Define feedback types
    public const TYPE_GENERAL = 'general';
    public const TYPE_BUG_REPORT = 'bug_report';
    public const TYPE_FEATURE_REQUEST = 'feature_request';

    // Define categories
    public const CATEGORY_UI = 'ui';
    public const CATEGORY_PERFORMANCE = 'performance';
    public const CATEGORY_DATA = 'data';
    public const CATEGORY_MAP = 'map';
    public const CATEGORY_AUTHENTICATION = 'authentication';
    public const CATEGORY_OTHER = 'other';

    // Define priorities
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_CRITICAL = 'critical';



    /**
     * Get the user who submitted the feedback.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }



    /**
     * Scope to filter by type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter by priority.
     */
    public function scopeWithPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Get all available types.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_GENERAL => 'General Feedback',
            self::TYPE_BUG_REPORT => 'Bug Report',
            self::TYPE_FEATURE_REQUEST => 'Feature Request',
        ];
    }

    /**
     * Get all available categories.
     */
    public static function getCategories(): array
    {
        return [
            self::CATEGORY_UI => 'User Interface',
            self::CATEGORY_PERFORMANCE => 'Performance',
            self::CATEGORY_DATA => 'Data Issues',
            self::CATEGORY_MAP => 'Map Functionality',
            self::CATEGORY_AUTHENTICATION => 'Authentication',
            self::CATEGORY_OTHER => 'Other',
        ];
    }

    /**
     * Get all available priorities.
     */
    public static function getPriorities(): array
    {
        return [
            self::PRIORITY_LOW => 'Low',
            self::PRIORITY_MEDIUM => 'Medium',
            self::PRIORITY_HIGH => 'High',
            self::PRIORITY_CRITICAL => 'Critical',
        ];
    }


}