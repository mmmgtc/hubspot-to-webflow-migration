# Migrate blog data from Hubspot to Webflow

This script takes existing blog content from Hubspot and syncs it with Webflow. Typical use is if you're migrating your blog content from Hubspot to Webflow.

## 1. Create a local .env

```
cp .env.example .env
```

## 2. Setup a Hubspot Private APP

In Hubspot, create a new Private App with the permissions below, and add the API key to the HUBSPOT_API_KEY environment variable:

```
content
crm.lists.read
crm.objects.contacts.read
crm.objects.marketing_events.read
crm.schemas.custom.read
crm.objects.custom.read
crm.schemas.contacts.read
crm.objects.companies.read
crm.objects.deals.read
crm.schemas.companies.read
crm.schemas.deals.read
crm.objects.owners.read
crm.objects.quotes.read
crm.schemas.quotes.read
crm.objects.line_items.read
crm.schemas.line_items.read
cms.performance.read
```

## 3. Setup a Webflow API key

Under Settings / Integrations for your Webflow domain, you'll find an API Access section. Generate a new API key, and add it to the WEBFLOW_API_KEY environment variable.

## 4. Setup the collections in Webflow

In our particular case, the standard "posts" for blog collection items wasn't enough, and we had to add "authors" and "tags" as additional collection items.

**posts**
name
slug
author
tags
post-body
post-summary
meta-title
meta-description
published-date
main-image

**authors**
name

**tags**
name

Once you have your 3 collections setup for posts, authors and tags, get the ids of each of these collections by clicking into the settings for a particular collection, where you should see "Collection ID".

These ids should be added into the environment variables for:

WEBFLOW_POSTS_RESOURCE=
WEBFLOW_TAGS_RESOURCE=
WEBFLOW_AUTHORS_RESOURCE=

## 5. Boot up your development environment

```
make up
```

## 6. Run the script

```
make go
```

This should run through all the blog posts in Hubspot and re-create them in Webflow if they don't already exist.
