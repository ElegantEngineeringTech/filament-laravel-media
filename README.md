# Filament Elegantly Media Plugin

## Installation

Install the plugin with Composer:

```bash
composer require elegantly/filament-media-plugin:"^4.0" -W
```

If you haven't already done so, you need to publish the migration to create the media table:

```bash
php artisan vendor:publish --tag="media-migrations"
```

Run the migrations:

```bash
php artisan migrate
```

You must also [prepare your Eloquent model](https://github.com/ElegantEngineeringTech/laravel-media#basic-usage) for attaching media.

> For more information, check out the [documentation](https://github.com/ElegantEngineeringTech/laravel-media).

## Form component

You may use the field in the same way as the [original file upload](https://filamentphp.com/docs/forms/file-upload) field:

```php
use Filament\Forms\Components\ElegantlyMediaFileUpload;

ElegantlyMediaFileUpload::make('avatar')
```

The media file upload supports all the customization options of the [original file upload component](https://filamentphp.com/docs/forms/file-upload).

### Passing a collection

Optionally, you may pass a [`collection()`](https://github.com/ElegantEngineeringTech/laravel-media#defining-media-collections) allows you to group files into categories:

```php
use Filament\Forms\Components\ElegantlyMediaFileUpload;

ElegantlyMediaFileUpload::make('avatar')
    ->collection('avatars')
```

### Configuring the storage disk and directory

By default, files will be uploaded publicly to your storage disk defined in the collection.

Alternatively, you can manually set the disk with the `disk()` method:

```php
use Filament\Forms\Components\ElegantlyMediaFileUpload;

ElegantlyMediaFileUpload::make('attachment')
    ->disk('s3')
```

### Reordering files

In addition to the behavior of the normal file upload, Elegantly's Media also allows users to reorder files.

To enable this behavior, use the `reorderable()` method:

```php
use Filament\Forms\Components\ElegantlyMediaFileUpload;

ElegantlyMediaFileUpload::make('attachments')
    ->multiple()
    ->reorderable()
```

You may now drag and drop files into order.

### Adding metadata

You may pass in [metadata] when uploading files using the `metadata()` method:

```php
use Filament\Forms\Components\ElegantlyMediaFileUpload;

ElegantlyMediaFileUpload::make('attachments')
    ->multiple()
    ->metadata(['zip_filename_prefix' => 'folder/subfolder/'])
```

### Using conversions

You may also specify a `conversion()` to load the file from showing it in the form, if present:

```php
use Filament\Forms\Components\ElegantlyMediaFileUpload;

ElegantlyMediaFileUpload::make('attachments')
    ->conversion('thumb')
```

### Filtering media

It's possible to target a file upload component to only handle a certain subset of media in a collection. To do that, you can filter the media collection using the `filterMediaUsing()` method. This method accepts a function that receives the `$media` collection and manipulates it. You can use any [collection method](https://laravel.com/docs/collections#available-methods) to filter it.

For example, you could scope the field to only handle media that has certain custom properties:

```php
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\ElegantlyMediaFileUpload;
use Illuminate\Support\Collection;

ElegantlyMediaFileUpload::make('images')
    ->metadata(fn (Get $get): array => [
        'gallery_id' => $get('gallery_id'),
    ])
    ->filterMediaUsing(
        fn (Collection $media, Get $get): Collection => $media->where(
            'metadata.gallery_id',
            $get('gallery_id')
        ),
    )
```

### Using media for rich editor file attachments

You can use media to store file attachments in the [rich editor](https://filamentphp.com/docs/forms/rich-editor). To do this, you must [register a rich content attribute](https://filamentphp.com/docs/forms/rich-editor#registering-rich-content-attributes) on your model, similar to how a media library collection is registered. You should call `fileAttachmentProvider()` on the attribute registration, passing in a `ElegantlyMediaFileAttachmentProvider::make()` object:

```php
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\ElegantlyMediaFileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Models\Concerns\InteractsWithRichContent;
use Filament\Forms\Components\RichEditor\Models\Contracts\HasRichContent;
use Illuminate\Database\Eloquent\Model;

class Post extends Model implements HasRichContent
{
    use InteractsWithRichContent;

    public function setUpRichContent(): void
    {
        $this->registerRichContent('content')
            ->fileAttachmentsProvider(ElegantlyMediaFileAttachmentProvider::make());
    }
}
```

A media collection with the same name as the attribute (`content` in this example) will be used for the file attachments. The collection must not contain any other media apart from file attachments for that attribute, since Filament will clear any unused media from the collection when the model is saved. To customize the name of the collection, you can pass it to the `collection()` method of the provider:

```php
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\ElegantlyMediaFileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Models\Concerns\InteractsWithRichContent;
use Filament\Forms\Components\RichEditor\Models\Contracts\HasRichContent;
use Illuminate\Database\Eloquent\Model;

class Post extends Model implements HasRichContent
{
    use InteractsWithRichContent;

    public function setUpRichContent(): void
    {
        $this->registerRichContent('content')
            ->fileAttachmentsProvider(
                ElegantlyMediaFileAttachmentProvider::make()
                    ->collection('content-file-attachments'),
            );
    }
}
```

## Table column

To use the media library image column:

```php
use Filament\Tables\Columns\ElegantlyMediaImageColumn;

ElegantlyMediaImageColumn::make('avatar')
```

The media library image column supports all the customization options of the [original image column](https://filamentphp.com/docs/tables/columns/image).

### Passing a collection

Optionally, you may pass a `collection()`:

```php
use Filament\Tables\Columns\ElegantlyMediaImageColumn;

ElegantlyMediaImageColumn::make('avatar')
    ->collection('avatars')
```

By default, only media without a collection (using the `default` collection) will be shown. If you want to show media from all collections, you can use the `allCollections()` method:

```php
use Filament\Tables\Columns\ElegantlyMediaImageColumn;

ElegantlyMediaImageColumn::make('avatar')
    ->allCollections()
```

### Using conversions

You may also specify a `conversion()` to load the file from showing it in the table, if present:

```php
use Filament\Tables\Columns\ElegantlyMediaImageColumn;

ElegantlyMediaImageColumn::make('avatar')
    ->conversion('thumb')
```

### Filtering media

It's possible to target the column to only display a subset of media in a collection. To do that, you can filter the media collection using the `filterMediaUsing()` method. This method accepts a function that receives the `$media` collection and manipulates it. You can use any [collection method](https://laravel.com/docs/collections#available-methods) to filter it.

For example, you could scope the column to only display media that has certain custom properties:

```php
use Filament\Tables\Columns\ElegantlyMediaImageColumn;
use Illuminate\Support\Collection;

ElegantlyMediaImageColumn::make('images')
    ->filterMediaUsing(
        fn (Collection $media): Collection => $media->where(
            'metadata.gallery_id',
            12345,
        ),
    )
```

## Infolist entry

To use the media library image entry:

```php
use Filament\Infolists\Components\ElegantlyMediaImageEntry;

ElegantlyMediaImageEntry::make('avatar')
```

The media library image entry supports all the customization options of the [original image entry](https://filamentphp.com/docs/infolists/entries/image).

### Passing a collection

Optionally, you may pass a `collection()`:

```php
use Filament\Infolists\Components\ElegantlyMediaImageEntry;

ElegantlyMediaImageEntry::make('avatar')
    ->collection('avatars')
```

By default, only media without a collection (using the `default` collection) will be shown. If you want to show media from all collections, you can use the `allCollections()` method:

```php
use Filament\Infolists\Components\ElegantlyMediaImageEntry;

ElegantlyMediaImageEntry::make('avatar')
    ->allCollections()
```

### Using conversions

You may also specify a `conversion()` to load the file from showing it in the infolist, if present:

```php
use Filament\Infolists\Components\ElegantlyMediaImageEntry;

ElegantlyMediaImageEntry::make('avatar')
    ->conversion('thumb')
```

### Filtering media

It's possible to target the entry to only display a subset of media in a collection. To do that, you can filter the media collection using the `filterMediaUsing()` method. This method accepts a function that receives the `$media` collection and manipulates it. You can use any [collection method](https://laravel.com/docs/collections#available-methods) to filter it.

For example, you could scope the entry to only display media that has certain custom properties:

```php
use Filament\Tables\Columns\ElegantlyMediaImageEntry;
use Illuminate\Support\Collection;

ElegantlyMediaImageEntry::make('images')
    ->filterMediaUsing(
        fn (Collection $media): Collection => $media->where(
            'metadata.gallery_id',
            12345,
        ),
    )
```
