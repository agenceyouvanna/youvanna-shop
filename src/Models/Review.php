<?php
namespace Youvanna\Shop\Models;

defined('ABSPATH') || exit;

class Review
{
    public ?int $id = null;
    public int $product_id = 0;
    public ?int $user_id = null;
    public string $author_name = '';
    public string $author_email = '';
    public int $rating = 5;
    public ?string $title = null;
    public string $content = '';
    public string $status = 'pending';
    public bool $verified_purchase = false;
    public ?string $created_at = null;

    public static function fromRow(array $row): self
    {
        $r = new self();
        $r->id = isset($row['id']) ? (int) $row['id'] : null;
        $r->product_id = (int) ($row['product_id'] ?? 0);
        $r->user_id = isset($row['user_id']) && $row['user_id'] !== null ? (int) $row['user_id'] : null;
        $r->author_name = (string) ($row['author_name'] ?? '');
        $r->author_email = (string) ($row['author_email'] ?? '');
        $r->rating = max(1, min(5, (int) ($row['rating'] ?? 5)));
        $r->title = $row['title'] ?? null;
        $r->content = (string) ($row['content'] ?? '');
        $r->status = (string) ($row['status'] ?? 'pending');
        $r->verified_purchase = !empty($row['verified_purchase']);
        $r->created_at = $row['created_at'] ?? null;
        return $r;
    }

    public function toDto(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'author_name' => $this->author_name,
            'rating' => $this->rating,
            'title' => $this->title,
            'content' => wp_kses_post($this->content),
            'verified_purchase' => $this->verified_purchase,
            'created_at' => $this->created_at,
        ];
    }
}
