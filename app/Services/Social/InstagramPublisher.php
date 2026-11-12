<?php

namespace App\Services\Social;

use App\Exceptions\SocialApiException;
use App\Models\SocialAccount;
use App\Models\SocialPostVariant;

/**
 * Instagram professional accounts through the Instagram Graph API (via the linked Facebook page token): create a
 * media container (or a carousel of item containers), then publish it. Container ids are stored on the variant at
 * once, so a retry publishes the same container — Instagram refuses to publish a container twice.
 */
class InstagramPublisher extends MetaPublisher
{
    public function publish(SocialPostVariant $variant, SocialAccount $account): PublishResult
    {
        $post = $variant->post;
        $user = (string) $account->external_id;
        $token = (string) $account->access_token;
        $images = $variant->mediaItems($post);

        if ($images->isEmpty()) {
            throw new SocialApiException(__('Instagram posts need at least one image.'));
        }

        $container = $this->remembered($variant, 'container');

        if (! is_string($container) || $container === '') {
            if ($images->count() === 1) {
                $media = $images->first();
                $container = (string) $this->call(fn () => $this->http()->asForm()->post($this->graph($user.'/media'), [
                    'image_url' => $this->mediaUrl($media),
                    'caption' => $variant->composedText($post),
                    'alt_text' => $this->altText($variant, $media),
                    'access_token' => $token,
                ]), __('Instagram media container'))->json('id');
            } else {
                $children = [];

                foreach ($images as $media) {
                    $child = $this->remembered($variant, 'children.m'.$media->id);

                    if (! is_string($child) || $child === '') {
                        $child = (string) $this->call(fn () => $this->http()->asForm()->post($this->graph($user.'/media'), [
                            'image_url' => $this->mediaUrl($media),
                            'is_carousel_item' => 'true',
                            'alt_text' => $this->altText($variant, $media),
                            'access_token' => $token,
                        ]), __('Instagram carousel item'))->json('id');
                        $this->remember($variant, 'children.m'.$media->id, $child);
                    }

                    $children[] = $child;
                }

                $container = (string) $this->call(fn () => $this->http()->asForm()->post($this->graph($user.'/media'), [
                    'media_type' => 'CAROUSEL',
                    'children' => implode(',', $children),
                    'caption' => $variant->composedText($post),
                    'access_token' => $token,
                ]), __('Instagram carousel container'))->json('id');
            }

            if ($container === '') {
                throw new SocialApiException(__('Instagram returned no media container id.'));
            }

            $this->remember($variant, 'container', $container);
        }

        $id = (string) $this->call(fn () => $this->http()->asForm()->post($this->graph($user.'/media_publish'), [
            'creation_id' => $container,
            'access_token' => $token,
        ]), __('Instagram publish'))->json('id');

        if ($id === '') {
            throw new SocialApiException(__('Instagram accepted the post but returned no id.'), SocialApiException::UNKNOWN);
        }

        $result = ['id' => $id, 'container' => $container];
        $url = null;

        try {
            $url = $this->call(fn () => $this->http()->get($this->graph($id), ['fields' => 'permalink', 'access_token' => $token]), __('Instagram permalink'))->json('permalink');
        } catch (SocialApiException) {
            // The post is live; the link can be added by hand.
        }

        if (filled($variant->first_comment)) {
            try {
                $result['first_comment'] = ['id' => (string) $this->call(fn () => $this->http()->asForm()->post($this->graph($id.'/comments'), [
                    'message' => (string) $variant->first_comment,
                    'access_token' => $token,
                ]), __('Instagram first comment'))->json('id')];
            } catch (SocialApiException $exception) {
                $result['first_comment'] = ['error' => $exception->getMessage()];
            }
        }

        return new PublishResult($id, is_string($url) ? $url : null, $result);
    }

    public function metrics(SocialPostVariant $variant, SocialAccount $account): array
    {
        $rows = collect($this->call(fn () => $this->http()->get($this->graph($variant->external_id.'/insights'), [
            'metric' => 'views,total_interactions',
            'access_token' => (string) $account->access_token,
        ]), __('Instagram insights'))->json('data', []))->mapWithKeys(fn (array $row): array => [$row['name'] ?? '' => $row['values'][0]['value'] ?? ($row['total_value']['value'] ?? null)]);

        return [
            'impressions' => is_numeric($rows['views'] ?? null) ? (int) $rows['views'] : null,
            'engagements' => is_numeric($rows['total_interactions'] ?? null) ? (int) $rows['total_interactions'] : null,
            // Instagram does not report link clicks for feed posts.
            'clicks' => null,
            'raw' => $rows->all(),
        ];
    }

    public function verify(SocialAccount $account): void
    {
        $this->call(fn () => $this->http()->get($this->graph((string) $account->external_id), [
            'fields' => 'id,username',
            'access_token' => (string) $account->access_token,
        ]), __('Instagram account'));
    }
}
