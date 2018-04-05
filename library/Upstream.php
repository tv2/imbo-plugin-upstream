<?php
namespace Tv2\Imbo\Plugins;

use Imbo\EventListener\ListenerInterface,
    Imbo\EventManager\EventInterface,
    Imbo\Exception\StorageException,
    ImboClient\ImboClient,
    ImboClient\ImagesQuery,
    Imbo\Model\Image;

/**
 * A event-listener for retrieving and storing unknown image-identifiers from a
 * upstream Imbo server (often the production instance)
 * Quite useful for local test-instances where you want to have production
 * assets available from a local instance, without having to replicate the full
 * storage and database - instead images will be fetched on demand.
 *
 * @author Morten Fangel <fangel@sevengoslings.net>
 * @package Event\Listeners
 */
class Upstream implements ListenerInterface {
  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'image.get' => ['checkUpstream' => 100],
    ];
  }

  protected $params = [
    'upstream' => null,
    'publicKey' => null,
    'privateKey' => null,
  ];

  public function __construct(array $params) {
    $this->params = array_merge($this->params, $params);
  }

  public function checkUpstream(EventInterface $event) {
    $request = $event->getRequest();
    $database = $event->getDatabase();
    $storage = $event->getStorage();

    $user = $request->getUser();
    $imageIdentifier = $request->getImageIdentifier();

    // Check if the image identifier exists locally - if it does, then we are
    // done here.
    if ($database->imageExists($user, $imageIdentifier)) {
      return null;
    }

    // The image didn't exist locally, so we try and see if the upstream server
    // has it.
    // First construct a ImboClient so we can query for the image
    $client = new ImboClient($this->params['upstream'], [
      'user' => $user,
      'publicKey' => $this->params['publicKey'],
      'privateKey' => $this->params['privateKey']
    ]);

    // Then make a query for the single id, with metadata attached.
    $query = new ImagesQuery();
    $query->ids([$imageIdentifier])->metadata(true);
    $results = $client->getImages($query);

    if ($results['search']['hits'] == 1) {
      // The upstream server had an image with that identifier
      $data = $results['images'][0];
      // Also retrieve the raw image-data
      $imageBlob = $client->getImageData($imageIdentifier);

      // Create a local image instance with the data we retrieved from the
      // upstream instance
      $image = new Image();
      $image->setMimeType($data['mime'])
            ->setExtension($data['extension'])
            ->setBlob($imageBlob)
            ->setWidth($data['width'])
            ->setHeight($data['height'])
            ->setOriginalChecksum($data['originalChecksum'])
            ->setMetadata($data['metadata'])
            ->setAddedDate($data['added'])
            ->setUpdatedDate($data['updated'])
            ->setUser($user)
            ->setImageIdentifier($imageIdentifier);

      // And then store this in the local storage and database.
      $database->insertImage($user, $imageIdentifier, $image);
      $storage->store($user, $imageIdentifier, $image->getBlob());

      // We fake a PUT-request to the metadata-resource to update the metadata
      // We do this, because it ensures that e.g. the Metadata Search plugin
      // will post the image to the search-index.
      // First we need to create a new request that contains the metadata as
      // the post-data, for the metadata-route
      $fakeRoute = new Route();
      $fakeRoute->setName('metadata');
      $fakeRoute->set('user', $request->getUser());
      $fakeRoute->set('imageIdentifier', $request->getImageIdentifier());
      $fakeRequest = Request::create($request->getRequestUri(), 'PUT', [], [], [], [], json_encode($image->getMetadata()));
      $fakeRequest->setRoute($fakeRoute);

      // And then we can dispatch the event as the 'metadata.put'-event.
      $event->getManager()->trigger('metadata.put', [
        'skipAccessControl' => TRUE,
        'request' => $fakeRequest,
      ]);
    }
  }
}
