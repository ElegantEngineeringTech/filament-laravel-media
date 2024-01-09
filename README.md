# Filament Finller Media Plugin

## Installation

Install the plugin with Composer:

```bash
composer require finller/laravel-media-plugin:"^3.1" -W
```

If you haven't already done so, you need to publish the migration to create the media table:

```bash
php artisan vendor:publish --tag="laravel-media-migrations"
```

Run the migrations:

```bash
php artisan migrate
```

You must also [prepare your Eloquent model](https://github.com/finller/laravel-media) for attaching media.

> For more information, check out [Finller's documentation](https://github.com/finller/laravel-media).

## Form component

You may use the field in the same way as the [original file upload](https://filamentphp.com/docs/forms/fields/file-upload) field:

```php
use Filament\Forms\Components\FinllerMediaFileUpload;

FinllerMediaFileUpload::make('avatar')
```

The media library file upload supports all the customization options of the [original file upload component](https://filamentphp.com/docs/forms/fields/file-upload).

> The field will automatically load and save its uploads to your model. To set this functionality up, **you must also follow the instructions set out in the [setting a form model](https://filamentphp.com/docs/forms/adding-a-form-to-a-livewire-component#setting-a-form-model) section**. If you're using a [panel](../panels), you can skip this step.

### Passing a collection

Optionally, you may pass a [`collection()`](https://github.com/finller/laravel-media) allows you to group files into categories:

```php
use Filament\Forms\Components\FinllerMediaFileUpload;

FinllerMediaFileUpload::make('avatar')
    ->collection('avatars')
```

### Configuring the storage disk and directory

By default, files will be uploaded publicly to your storage disk defined in the [Filament configuration file](https://filamentphp.com/docs/forms/installation#publishing-configuration). You can also set the `FILAMENT_FILESYSTEM_DISK` environment variable to change this. This is to ensure consistency between all Filament packages. Finller's disk configuration will not be used, unless you [define a disk for a registered collection](https://github.com/finller/laravel-media).

Alternatively, you can manually set the disk with the `disk()` method:

```php
use Filament\Forms\Components\FileUpload;

FileUpload::make('attachment')
    ->disk('s3')
```

The base file upload component also has configuration options for setting the `directory()` and `visibility()` of uploaded files. These are not used by the media library file upload component. Finller's package has its own system for determining the directory of a newly-uploaded file, and it does not support uploading private files out of the box. One way to store files privately is to configure this in your S3 bucket settings, in which case you should also use `visibility('private')` to ensure that Filament generates temporary URLs for your files.

### Reordering files

In addition to the behaviour of the normal file upload, Finller's Media Library also allows users to reorder files.

To enable this behaviour, use the `reorderable()` method:

```php
use Filament\Forms\Components\FinllerMediaFileUpload;

FinllerMediaFileUpload::make('attachments')
    ->multiple()
    ->reorderable()
```

You may now drag and drop files into order.

### Adding custom properties

You may pass in [custom properties](https://github.com/finller/laravel-media) when uploading files using the `customProperties()` method:

```php
use Filament\Forms\Components\FinllerMediaFileUpload;

FinllerMediaFileUpload::make('attachments')
    ->multiple()
    ->customProperties(['zip_filename_prefix' => 'folder/subfolder/'])
```

### Using conversions

You may also specify a `conversion()` to load the file from showing it in the form, if present:

```php
use Filament\Forms\Components\FinllerMediaFileUpload;

FinllerMediaFileUpload::make('attachments')
    ->conversion('thumb')
```

#### Storing conversions on a separate disk

You can store your conversions and responsive images on a disk other than the one where you save the original file. Pass the name of the disk where you want conversion to be saved to the `conversionsDisk()` method:

```php
use Filament\Forms\Components\FinllerMediaFileUpload;

FinllerMediaFileUpload::make('attachments')
    ->conversionsDisk('s3')
```

## Table column

To use the media library image column:

```php
use Filament\Tables\Columns\FinllerMediaImageColumn;

FinllerMediaImageColumn::make('avatar')
```

The media library image column supports all the customization options of the [original image column](https://filamentphp.com/docs/tables/columns/image).

### Passing a collection

Optionally, you may pass a `collection()`:

```php
use Filament\Tables\Columns\FinllerMediaImageColumn;

FinllerMediaImageColumn::make('avatar')
    ->collection('avatars')
```

### Using conversions

You may also specify a `conversion()` to load the file from showing it in the table, if present:

```php
use Filament\Tables\Columns\FinllerMediaImageColumn;

FinllerMediaImageColumn::make('avatar')
    ->conversion('thumb')
```

## Infolist entry

To use the media library image entry:

```php
use Filament\Infolists\Components\FinllerMediaImageEntry;

FinllerMediaImageEntry::make('avatar')
```

The media library image entry supports all the customization options of the [original image entry](https://filamentphp.com/docs/infolists/entries/image).

### Passing a collection

Optionally, you may pass a `collection()`:

```php
use Filament\Infolists\Components\FinllerMediaImageEntry;

FinllerMediaImageEntry::make('avatar')
    ->collection('avatars')
```

### Using conversions

You may also specify a `conversion()` to load the file from showing it in the infolist, if present:

```php
use Filament\Infolists\Components\FinllerMediaImageEntry;

FinllerMediaImageEntry::make('avatar')
    ->conversion('thumb')
```
