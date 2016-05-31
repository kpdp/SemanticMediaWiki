<?php

namespace SMW;

use SMW\Query\DescriptionFactory;
use SMWDIBlob as DIBlob;
use SMWQuery as Query;

/**
 * This class should be accessed via ApplicationFactory::getPropertySpecificationLookup
 * to ensure a singleton instance.
 *
 * @license GNU GPL v2+
 * @since 2.4
 *
 * @author mwjames
 */
class PropertySpecificationLookup {

	const POOLCACHE_ID = 'property.specification.lookup';

	/**
	 * @var CachedPropertyValuesPrefetcher
	 */
	private $cachedPropertyValuesPrefetcher;

	/**
	 * @var string
	 */
	private $languageCode = 'en';

	/**
	 * @var FixedInMemoryCache
	 */
	private $intermediaryMemoryCache;

	/**
	 * @since 2.4
	 *
	 * @param CachedPropertyValuesPrefetcher $cachedPropertyValuesPrefetcher
	 */
	public function __construct( CachedPropertyValuesPrefetcher $cachedPropertyValuesPrefetcher ) {
		$this->cachedPropertyValuesPrefetcher = $cachedPropertyValuesPrefetcher;
		$this->intermediaryMemoryCache = ApplicationFactory::getInstance()->getInMemoryPoolCache()->getPoolCacheById( self::POOLCACHE_ID );
	}

	/**
	 * @since 2.4
	 */
	public function resetCacheBy( DIWikiPage $subject ) {
		$this->cachedPropertyValuesPrefetcher->resetCacheBy( $subject );
	}

	/**
	 * @since 2.4
	 *
	 * @param string
	 */
	public function getLanguageCode() {
		return $this->languageCode;
	}

	/**
	 * @since 2.4
	 *
	 * @param string $languageCode
	 */
	public function setLanguageCode( $languageCode ) {
		$this->languageCode = Localizer::asBCP47FormattedLanguageCode( $languageCode );
	}

	/**
	 * @since 2.4
	 *
	 * @param string $displayTitle
	 *
	 * @return DIProperty|false
	 */
	public function getPropertyFromDisplayTitle( $displayTitle ) {

		$descriptionFactory = new DescriptionFactory();

		$description = $descriptionFactory->newSomeProperty(
			new DIProperty( '_DTITLE' ),
			$descriptionFactory->newValueDescription( new DIBlob( $displayTitle ) )
		);

		$query = new Query( $description );
		$query->setLimit( 1 );

		$dataItems = $this->cachedPropertyValuesPrefetcher->queryPropertyValuesWith(
			$query
		);

		if ( is_array( $dataItems ) && $dataItems !== array() ) {
			$dataItem = end( $dataItems );

			// Cache results as a linked list attached to
			// the property so that it can be purged all together

			return new DIProperty( $dataItem->getDBKey() );
		}

		return false;
	}

	/**
	 * @since 2.4
	 *
	 * @param DIProperty $property
	 *
	 * @return boolean
	 */
	public function hasUniquenessConstraintBy( DIProperty $property ) {

		$hasUniquenessConstraint = false;
		$key = $property->getKey();

		// Guard against high frequency lookup
		if ( $this->intermediaryMemoryCache->contains( 'uc:'. $key ) ) {
			return $this->intermediaryMemoryCache->fetch( 'uc:'. $key );
		}

		$dataItems = $this->cachedPropertyValuesPrefetcher->getPropertyValues(
			$property->getDiWikiPage(),
			new DIProperty( '_PVUC' )
		);

		if ( is_array( $dataItems ) && $dataItems !== array() ) {
			$hasUniquenessConstraint = end( $dataItems )->getBoolean();
		}

		$this->intermediaryMemoryCache->save( 'uc:'. $key, $hasUniquenessConstraint );

		return $hasUniquenessConstraint;
	}

	/**
	 * @since 2.5
	 *
	 * @param DIProperty $property
	 *
	 * @return DataItem|null
	 */
	public function getExternalFormatterUriBy( DIProperty $property ) {

		$dataItem = null;

		$dataItems = $this->cachedPropertyValuesPrefetcher->getPropertyValues(
			$property->getDiWikiPage(),
			new DIProperty( '_PEFU' )
		);

		if ( is_array( $dataItems ) && $dataItems !== array() ) {
			$dataItem = end( $dataItems );
		}

		return $dataItem;
	}

	/**
	 * @since 2.4
	 *
	 * @param DIProperty $property
	 *
	 * @return string
	 */
	public function getAllowedPatternBy( DIProperty $property ) {

		$allowsPattern = '';
		$key = 'ap:'. $property->getKey();

		// Guard against high frequency lookup
		if ( $this->intermediaryMemoryCache->contains( $key ) ) {
			return $this->intermediaryMemoryCache->fetch( $key );
		}

		$dataItems = $this->cachedPropertyValuesPrefetcher->getPropertyValues(
			$property->getDiWikiPage(),
			new DIProperty( '_PVAP' )
		);

		if ( is_array( $dataItems ) && $dataItems !== array() ) {
			$allowsPattern = end( $dataItems )->getString();
		}

		$this->intermediaryMemoryCache->save( $key, $allowsPattern );

		return $allowsPattern;
	}

	/**
	 * @since 2.4
	 *
	 * @param DIProperty $property
	 *
	 * @return integer|false
	 */
	public function getAllowedValuesBy( DIProperty $property ) {

		$allowsValues = array();
		$key = 'al:'. $property->getKey();

		// Guard against high frequency lookup
		if ( $this->intermediaryMemoryCache->contains( $key ) ) {
			return $this->intermediaryMemoryCache->fetch( $key );
		}

		$dataItems = $this->cachedPropertyValuesPrefetcher->getPropertyValues(
			$property->getDiWikiPage(),
			new DIProperty( '_PVAL' )
		);

		if ( is_array( $dataItems ) && $dataItems !== array() ) {
			$allowsValues = $dataItems;
		}

		$this->intermediaryMemoryCache->save( $key, $allowsValues );

		return $allowsValues;
	}

	/**
	 * @since 2.4
	 *
	 * @param DIProperty $property
	 *
	 * @return integer|false
	 */
	public function getDisplayPrecisionBy( DIProperty $property ) {

		$displayPrecision = false;

		$dataItems = $this->cachedPropertyValuesPrefetcher->getPropertyValues(
			$property->getDiWikiPage(),
			new DIProperty( '_PREC' )
		);

		if ( $dataItems !== false && $dataItems !== array() ) {
			$dataItem = end( $dataItems );
			$displayPrecision = abs( (int)$dataItem->getNumber() );
		}

		return $displayPrecision;
	}

	/**
	 * @since 2.4
	 *
	 * @param DIProperty $property
	 *
	 * @return array
	 */
	public function getDisplayUnitsBy( DIProperty $property ) {

		$units = array();

		$dataItems = $this->cachedPropertyValuesPrefetcher->getPropertyValues(
			$property->getDiWikiPage(),
			new DIProperty( '_UNIT' )
		);

		if ( $dataItems !== false && $dataItems !== array() ) {
			foreach ( $dataItems as $dataItem ) {
				$units = array_merge( $units, preg_split( '/\s*,\s*/u', $dataItem->getString() ) );
			}
		}

		return $units;
	}

	/**
	 * We try to cache anything to avoid unnecessary store connections or DB
	 * lookups. For cases where a property was changed, the EventDipatcher will
	 * receive a 'property.spec.change' event (emitted as soon as the content of
	 * a property page was altered) with PropertySpecificationLookup::resetCacheBy
	 * being invoked to remove the cache entry for that specific property.
	 *
	 * @since 2.4
	 *
	 * @param DIProperty $property
	 * @param mixed|null $linker
	 *
	 * @return string
	 */
	public function getPropertyDescriptionBy( DIProperty $property, $linker = null ) {

		// Take the linker into account (Special vs. in page rendering etc.)
		$key = '--pdesc:' . $this->languageCode . ':' . ( $linker === null ? '0' : '1' );

		$blobStore = $this->cachedPropertyValuesPrefetcher->getBlobStore();

		$container = $blobStore->read(
			$this->cachedPropertyValuesPrefetcher->getRootHashFrom( $property->getDiWikiPage() )
		);

		if ( $container->has( $key ) ) {
			return $container->get( $key );
		}

		$localPropertyDescription = $this->tryToFindLocalPropertyDescription(
			$property,
			$linker
		);

		// If a local property description wasn't available for a predefined property
		// the try to find a system translation
		if ( trim( $localPropertyDescription ) === '' && !$property->isUserDefined() ) {
			$localPropertyDescription = $this->getPredefinedPropertyDescription( $property, $linker );
		}

		$container->set( $key, $localPropertyDescription );

		$blobStore->save(
			$container
		);

		return $localPropertyDescription;
	}

	private function getPredefinedPropertyDescription( $property, $linker ) {

		$description = '';
		$key = $property->getKey();

		if ( ( $msgKey = PropertyRegistry::getInstance()->findPropertyDescriptionMsgKeyById( $key ) ) === '' ) {
			$msgKey = 'smw-pa-property-predefined' . strtolower( $key );
		}

		if ( !wfMessage( $msgKey )->exists() ) {
			return $description;
		}

		$message = wfMessage( $msgKey, $property->getLabel() )->inLanguage(
			$this->languageCode
		);

		return $linker === null ? $message->escaped() : $message->parse();
	}

	private function tryToFindLocalPropertyDescription( $property, $linker ) {

		$description = '';

		$dataItems = $this->cachedPropertyValuesPrefetcher->getPropertyValues(
			$property->getDiWikiPage(),
			new DIProperty( '_PDESC' )
		);

		if ( ( $dataValue = $this->findDataValueByLanguage( $dataItems, $this->languageCode ) ) !== null ) {
			$description = $dataValue->getShortWikiText( $linker );
		}

		return $description;
	}

	private function findDataValueByLanguage( $dataItems, $languageCode ) {

		if ( $dataItems === null || $dataItems === array() ) {
			return null;
		}

		foreach ( $dataItems as $dataItem ) {

			$dataValue = DataValueFactory::getInstance()->newDataValueByItem(
				$dataItem,
				new DIProperty( '_PDESC' )
			);

			// Here a MonolingualTextValue was retunred therefore the method
			// can be called without validation
			$dv = $dataValue->getTextValueByLanguage( $languageCode );

			if ( $dv !== null ) {
				return $dv;
			}
		}

		return null;
	}

}
