<?php

namespace App\Http\Controllers;

use Exception;
use GuzzleHttp\Client;
use HubSpot\Client\Cms\Blogs\BlogPosts\Model\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use tidy;

class APIController extends Controller
{
    public $cacheTimeout = 12000;

    public function __construct()
    {
    }

    public function getWebflowCollectionItems($collectionId)
    {
        $cacheName = 'getWebflowCollectionItems-' . $collectionId;
        if (Cache::get($cacheName)) {
            return Cache::get($cacheName);
        }

        $webflow = $this->getWebflowInstance();
        $items = $webflow->itemsAll($collectionId);
        $return = [];
        foreach ($items as $key => $value) {
            $return[$value->_id] = $value->name;
        }

        Cache::put($cacheName, $return, $this->cacheTimeout);

        return $return;
    }

    public function getHubspotPosts()
    {
        $hubspot = \HubSpot\Factory::createWithAccessToken(env('HUBSPOT_API_KEY'));

        $cacheName = 'getHubspotPosts';
        if (Cache::get($cacheName)) {
            $results = Cache::get($cacheName);
        } else {
            $results = [];

            $after = '';
            for ($i = 0; $i <= 100; $i++) {
                $this->msg('Get paginated posts: ' . $i);
                $response = $hubspot->apiRequest([
                    'path' => '/cms/v3/blogs/posts' . $after,
                ]);
                $data = json_decode($response->getBody()->getContents(), true);
                $results = array_merge($results, $data['results']);
                if (isset($data['paging']['next']['after']) && strlen($data['paging']['next']['after']) > 0) {
                    $after = '?after=' . $data['paging']['next']['after'];
                } else {
                    break;
                }
            }
            Cache::put($cacheName, $results, $this->cacheTimeout);
        }

        return $results;
    }


    public function addHubspotPosts()
    {
        $this->msg('Get Hubspot Posts');
        $webflowAuthors = $this->getWebflowCollectionItems(env('WEBFLOW_AUTHORS_RESOURCE'));
        $hubspotAuthors = $this->getHubspotAuthors();
        $this->msg('Hubspot Authors: ' . count($hubspotAuthors));
        $this->msg('Webflow Authors: ' . count($webflowAuthors));

        $webflowTags = $this->getWebflowCollectionItems(env('WEBFLOW_TAGS_RESOURCE'));
        $hubspotTags = $this->getHubspotTags();
        $this->msg('Webflow Tags: ' . count($webflowTags));
        $this->msg('Hubspot Tags: ' . count($hubspotTags));

        $hubspot = \HubSpot\Factory::createWithAccessToken(env('HUBSPOT_API_KEY'));

        $lastArticle = '';
        $cacheName = 'addHubspotPosts28' . md5($lastArticle);
        if (Cache::get($cacheName)) {
            $results = Cache::get($cacheName);
        } else {
            $results = [];

            $after = '';
            for ($i = 0; $i <= 100; $i++) {
                $this->msg('Get paginated posts: ' . $i);
                $response = $hubspot->apiRequest([
                    'path' => '/cms/v3/blogs/posts' . $after,
                ]);
                $data = json_decode($response->getBody()->getContents(), true);
                $results = array_merge($results, $data['results']);
                if (isset($data['paging']['next']['after']) && strlen($data['paging']['next']['after']) > 0) {
                    $after = '?after=' . $data['paging']['next']['after'];
                } else {
                    break;
                }
            }
            Cache::put($cacheName, $results, $this->cacheTimeout);
        }

        $canContinue = false;
        foreach ($results as $key => $value) {

            $this->msg('Name: ' . $key . ' - ' . $value['name']);
            if ($lastArticle !== '') {
                if ($value['name'] === $lastArticle) {
                    $canContinue = true;
                }

                if (!$canContinue) {
                    continue;
                }
            }

            $webflowTagSelected = [];
            foreach ($value['tagIds'] as $key2 => $value2) {
                $tagName = $hubspotTags[$value2];
                $webflowTagId = array_search($tagName, $webflowTags);
                $webflowTagSelected[] = $webflowTagId;
            }

            $webflowAuthorSelected = null;
            if (isset($value['blogAuthorId'])) {
                $authorName = $hubspotAuthors[$value['blogAuthorId']];
                $webflowAuthorSelected = array_search($authorName, $webflowAuthors);
            }

            $search = ['blog/', '/', '.'];
            $replace = ['', '', ''];
            $slug = str_replace($search, $replace, $value['slug']);

            $return = $this->webflowAddCollectionItem(env('WEBFLOW_POSTS_RESOURCE'), [
                'name' => $value['name'],
                'slug' => $slug,
                'author' => $webflowAuthorSelected,
                'tags' => $webflowTagSelected,
                'post-body' => $this->cleanHTML($value['postBody']),
                'post-summary' => $this->characterLimiter(strip_tags($value['postSummary'], 100)),
                'meta-title' => $value['htmlTitle'],
                'meta-description' => $value['metaDescription'],
                'published-date' => $value['publishDate'],
                'main-image' => $value['featuredImage'],
            ]);
        }
    }


    private function cleanHTML($html)
    {
        $search = ['<header>', '</header>'];
        $replace = ['', ''];
        $html = str_replace($search, $replace, $html);

        $tidy = new tidy();
        $clean = $tidy->repairString($html, [
            'clean' => true,
            'drop-empty-elements' => true,
            'drop-proprietary-attributes' => true,
            'output-html' => true,
            'merge-divs' => true,
            'merge-spans' => true,
            'show-body-only' => true,

        ]);
        return $clean;
    }

    private function characterLimiter($str, $n = 500, $end_char = '&#8230;')
    {
        if (strlen($str) < $n) {
            return $str;
        }

        $str = preg_replace("/\s+/", ' ', str_replace(array("\r\n", "\r", "\n"), ' ', $str));

        if (strlen($str) <= $n) {
            return $str;
        }

        $out = "";
        foreach (explode(' ', trim($str)) as $val) {
            $out .= $val . ' ';

            if (strlen($out) >= $n) {
                $out = trim($out);
                return (strlen($out) == strlen($str)) ? $out : $out . $end_char;
            }
        }
    }

    public function webflowAddCollectionItem($collectionId, $data)
    {
        $webflow = $this->getWebflowInstance();

        $this->msg('webflowAddCollectionItem: ' . $data['name']);

        try {
            // Create or update
            $this->msg('create or update');
            $obj = ($webflow->findOrCreateItemByName($collectionId, $data));
            $itemId = $obj->_id;

            // Make live
            $this->msg('make live');
            $obj = ($webflow->updateItem($collectionId, $itemId, array_merge($data, ['_archived' => false, '_draft' => false]), true));
        } catch (Exception $e) {
            dd($e->getMessage());
        }
    }

    private function countHubspotWords($results)
    {

        $total = 0;
        foreach ($results as $key => $value) {
            if (isset($value['postBody'])) {

                $stripped = strip_tags($value['postBody']);
                $words = count(explode(' ', $stripped));
                $total += $words;
            }
        }

        return $total;
    }


    public function webflowCollectionList($collectionId)
    {
        $webflow = $this->getWebflowInstance();
        $data = $webflow->itemsAll($collectionId);

        return $data;
    }

    public function webflowCollectionItemList($collectionId, $itemId)
    {
        $webflow = $this->getWebflowInstance();
        $data = $webflow->item($collectionId, $itemId);

        return $data;
    }

    private function getWebflowInstance()
    {
        return new \Webflow\Api(env('WEBFLOW_API_KEY'));
    }

    private function msg($msg)
    {
        echo $msg . PHP_EOL;
    }


    public function getHubspotTags()
    {
        $cacheName = 'getHubspotTags';
        if (Cache::get($cacheName)) {
            return Cache::get($cacheName);
        }

        $this->msg('Get Hubspot Tags');
        $hubspot = \HubSpot\Factory::createWithAccessToken(env('HUBSPOT_API_KEY'));

        $results = [];

        $after = '';
        for ($i = 0; $i <= 1000; $i++) {
            $response = $hubspot->apiRequest([
                'path' => '/cms/v3/blogs/tags' . $after,
            ]);
            $data = json_decode($response->getBody()->getContents(), true);
            $results = array_merge($results, $data['results']);
            if (isset($data['paging']['next']['after'])) {
                $after = '?after=' . $data['paging']['next']['after'];
            } else {
                break;
            }
        }

        $return = [];
        foreach ($results as $key => $value) {
            $return[$value['id']] = $value['name'];
        }

        Cache::put($cacheName, $return, $this->cacheTimeout);

        return $return;
    }

    public function getHubspotAuthors()
    {
        $cacheName = 'getHubspotAuthors';
        if (Cache::get($cacheName)) {
            return Cache::get($cacheName);
        }

        $this->msg('Get Hubspot Authors');

        $hubspot = \HubSpot\Factory::createWithAccessToken(env('HUBSPOT_API_KEY'));

        $results = [];

        $after = '';
        for ($i = 0; $i <= 1000; $i++) {
            $response = $hubspot->apiRequest([
                'path' => '/cms/v3/blogs/authors',
            ]);
            $data = json_decode($response->getBody()->getContents(), true);
            $results = array_merge($results, $data['results']);
            if (isset($data['paging']['next']['after'])) {
                $after = '?after=' . $data['paging']['next']['after'];
            } else {
                break;
            }
        }

        $return = [];

        foreach ($results as $key => $value) {
            $return[$value['id']] = $value['name'];
        }
        Cache::put($cacheName, $return, $this->cacheTimeout);

        return $return;
    }
}
