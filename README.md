# Ximilar API

A Drupal module for integrating with the Ximilar Image API.

## Installation

1. Sign up for an account at [Ximilar](https://app.ximilar.com/)
2. Enable the module.
3. Configure the module at `/admin/config/media/ximilar-api`.
4. Any image fields that are to be processed by Ximilar should be configured to use the "Ximilar API" widget on their form display.
   git remote set-url origin
## Todo

- Prompt the user to choose whether to continue with upload or abort, via  a modal. Base the modal upon the confirmation modal that appears when deleting a media item from the media list view.
- Revise ImageWidget:process() - this is where the upload field is replaced with the image thumbnail. We want to add a new step before processing where the user can confirm the upload.
- Remove images from the collection when they are deleted.
