# Ximilar API

A Drupal module for integrating with the Ximilar Image API.

## Installation

1. Sign up for an account at [Ximilar](https://app.ximilar.com/)
   1. Obtain an authorisation token.
   2. Create a [collection](https://app.ximilar.com/similarity/collections).
2. Enable the module.
3. Configure the module at `/admin/config/media/ximilar-api`.
4. Any image fields that are to be processed by Ximilar should be configured to use the "Ximilar API" widget on their form display.
   git remote set-url origin


## Todo
- Add requirements alert for collection id and authorisation token when on the media sync page
- Setup process to sync media to a collection. Batch process required which starts after submitting `/admin/config/media/ximilar-api/media-sync`.
- Prompt the user to choose whether to continue with upload or abort, via  a modal. Base the modal upon the confirmation modal that appears when deleting a media item from the media list view.
- Revise ImageWidget:process() - this is where the upload field is replaced with the image thumbnail. We want to add a new step before processing where the user can confirm the upload.
- Remove images from the collection when they are deleted.

### As a developer I want
- an easy way of seeing whether similar images exist in the collection to the one that I provide

### As a content editor I want
- a page where I can find and remove duplicate images that are in the Media Library
- a page where I can check the library for a duplicate image in the Media Library to the one that I possess
- a page where I can browse the contents of the Ximilar collection
- a page where I can create tags based on an image
- a page where I can search my media library by text
