<?php

declare(strict_types=1);

/*
 * @copyright eBlick Medienberatung
 * @license proprietary
 */

namespace EBlick\ContaoOpenImmoImport\Import\Data;

use Ujamii\OpenImmo\API\Anhang;
use Ujamii\OpenImmo\API\Immobilie;
use Ujamii\OpenImmo\API\Unterkellert;

class Normalizer
{
    /**
     * Reads an @see Immobilie object and outputs it in normalized form.
     *
     * @throws NormalizationException
     */
    public function normalize(string $anbieterNr, Immobilie $immobilie): ObjectData
    {
        $objectId = $immobilie->getVerwaltungTechn()?->getOpenimmoObid();

        if (!\is_string($objectId) || '' === $objectId) {
            throw new NormalizationException('Objekt enthält keine gültige Objekt-ID.');
        }

        return new ObjectData(
            $anbieterNr,
            $objectId,
            $this->compileObjectProperties($immobilie),
            $this->compileAgentData($immobilie),
            $this->compileResourceData($immobilie)
        );
    }

    /**
     * @return array<string, int|string|null>
     */
    private function compileObjectProperties(Immobilie $immobilie): array
    {
        // Gather data
        $verwaltungObjekt = $immobilie->getVerwaltungObjekt();
        $objektKategorie = $immobilie->getObjektkategorie();
        $nutzungsart = $objektKategorie?->getNutzungsart();
        $objektart = $objektKategorie?->getObjektart();
        $zustand = $immobilie->getZustandAngaben();
        $freitexte = $immobilie->getFreitexte();
        $flaechen = $immobilie->getFlaechen();
        $geo = $immobilie->getGeo();
        $geoKoordinaten = $geo?->getGeokoordinaten();
        $preise = $immobilie->getPreise();
        $ausstattung = $immobilie->getAusstattung();
        $bad = $ausstattung?->getBad();
        $kueche = $ausstattung?->getKueche();
        $boden = $ausstattung?->getBoden();
        $heizungsart = $ausstattung?->getHeizungsart();
        $fahrstuhl = $ausstattung?->getFahrstuhl();
        $ausrichtungBalkonTerrasse = $ausstattung?->getAusrichtBalkonTerrasse();

        $stellplatzArt = $ausstattung?->getStellplatzart()[0] ?? null;
        $energieAusweis = $zustand?->getEnergiepass()[0] ?? null;

        $stellplatzGarage = $preise?->getStpGarage();
        $stellplatzCarport = $preise?->getStpCarport();
        $stellplatzFreiplatz = $preise?->getStpFreiplatz();

        $objektartMap = [
            'Haus' => ($objektart?->getHaus()[0] ?? null)?->getHaustyp(),
            'Wohnung' => ($objektart?->getWohnung()[0] ?? null)?->getWohnungtyp(),
            'Grundstück' => ($objektart?->getGrundstueck()[0] ?? null)?->getGrundstTyp(),
            'Zimmer' => ($objektart?->getZimmer()[0] ?? null)?->getZimmertyp(),
            'Büro/Praxen' => ($objektart?->getBueroPraxen()[0] ?? null)?->getBueroTyp(),
            'Laden/Einzelhandel' => ($objektart?->getEinzelhandel()[0] ?? null)?->getHandelTyp(),
            'Sonstige' => ($objektart?->getSonstige()[0] ?? null)?->getSonstigeTyp(),
        ];

        $objektartKey = array_key_first(array_filter($objektartMap)) ?: '';
        $objektartValue = ucfirst(strtolower($objektartMap[$objektartKey] ?? ''));

        $distanzenMap = [];

        foreach ($immobilie->getInfrastruktur()?->getDistanzen() ?? [] as $item) {
            $distanzenMap[$item->getDistanzZu()] = $item->getValue();
        }

        // Compile record
        return [
            // Veröffentlichung + allgmeine Metadaten
            'verfuegbar_ab' => (string) $verwaltungObjekt?->getVerfuegbarAb(),
            'abdatum' => $verwaltungObjekt?->getAbdatum()?->format('d.m.Y') ?? '',
            'bisdatum' => $verwaltungObjekt?->getBisdatum()?->format('d.m.Y') ?? '',
            'user' => (string) $immobilie->getKontaktperson()?->getEmailZentrale(),

            // Kategorie
            'nutzungsart' => $this->serializeFlags([
                'Wohnen' => $nutzungsart?->getWohnen() ?? false,
                'Gewerbe' => $nutzungsart?->getGewerbe() ?? false,
                'Anlage' => $nutzungsart?->getAnlage() ?? false,
                'WAZ' => $nutzungsart?->getWaz() ?? false,
            ]),
            'objektart' => $objektartKey,
            'objekttyp' => $objektartValue,

            // Zustand
            'baujahr' => (int) $zustand?->getBaujahr(),
            'zustand' => (string) $zustand?->getZustand()?->getZustandArt(),

            // Titel + Freitexte
            'objekttitel' => (string) $freitexte?->getObjekttitel(),
            'dreizeiler' => $freitexte?->getDreizeiler(),
            'objektbeschreibung' => $freitexte?->getObjektbeschreibung(),
            'lage' => $freitexte?->getLage(),
            'ausstatt_beschr' => $freitexte?->getAusstattBeschr(),
            'sonstige_angaben' => $freitexte?->getSonstigeAngaben(),

            // Geo
            'adresse_street' => sprintf('%s %s', $geo?->getStrasse(), $geo?->getHausnummer()),
            'adresse_city' => (string) $geo?->getOrt(),
            'adresse_zipcode' => (string) $geo?->getPlz(),
            'adresse_country' => (string) $geo?->getLand()?->getIsoLand(),
            'bundesland' => (string) $geo?->getBundesland(),
            'adresse' => sprintf('%s %s', $geoKoordinaten?->getBreitengrad(), $geoKoordinaten?->getLaengengrad()),
            'objektadresse_freigeben' => $this->asCharBool($verwaltungObjekt?->getObjektadresseFreigeben()),

            // Preise + Kosten
            'waehrung' => (string) $preise?->getWaehrung()?->getIsoWaehrung(),
            'kaufpreis' => $this->formatMoney($preise?->getKaufpreis()?->getValue()),
            'provisionspflichtig' => $this->asCharBool($preise?->getProvisionspflichtig()),
            'aussenprovision' => (string) $preise?->getAussenCourtage()?->getValue(),
            'innenprovision' => (string) $preise?->getInnenCourtage()?->getValue(),
            'nebenkosten' => (string) $preise?->getNebenkosten(),
            'betriebskostennetto' => (string) $preise?->getBetriebskostennetto()?->getValue(),
            'heizkosten' => (string) $preise?->getHeizkosten(),
            'heizkosten_enthalten' => $this->asCharBool($preise?->getHeizkostenEnthalten()),
            'stp_freiplatz_preis' => $this->formatMoney(
                $stellplatzFreiplatz?->getStellplatzkaufpreis() ?? $stellplatzFreiplatz?->getStellplatzmiete()
            ),
            'stp_carport_preis' => $this->formatMoney(
                $stellplatzCarport?->getStellplatzkaufpreis() ?? $stellplatzCarport?->getStellplatzmiete()
            ),
            'stp_garage_preis' => $this->formatMoney(
                $stellplatzGarage?->getStellplatzkaufpreis() ?? $stellplatzGarage?->getStellplatzmiete()
            ),

            // Flächen
            'wohnflaeche' => (string) $flaechen?->getWohnflaeche(),
            'nutzflaeche' => (string) $flaechen?->getNutzflaeche(),
            'gesamtflaeche' => (string) $flaechen?->getGesamtflaeche(),
            'gartenflaeche' => (string) $flaechen?->getGartenflaeche(),
            'bueroflaeche' => (string) $flaechen?->getBueroflaeche(),
            'ladenflaeche' => (string) $flaechen?->getLadenflaeche(),
            'lagerflaeche' => (string) $flaechen?->getLagerflaeche(),
            'gastroflaeche' => (string) $flaechen?->getGastroflaeche(),
            'verkaufsflaeche' => (string) $flaechen?->getVerkaufsflaeche(),
            'vermietbare_flaeche' => (string) $flaechen?->getVermietbareFlaeche(),

            // Räume
            'anzahl_zimmer' => (string) $flaechen?->getAnzahlZimmer(),
            'anzahl_schlafzimmer' => (string) $flaechen?->getAnzahlSchlafzimmer(),
            'anzahl_badezimmer' => (string) $flaechen?->getAnzahlBadezimmer(),
            'anzahl_balkone' => (string) $flaechen?->getAnzahlBalkone(),
            'anzahl_terrassen' => (string) $flaechen?->getAnzahlTerrassen(),

            // Stellplätze
            'anzahl_garagen' => (string) $preise?->getStpGarage()?->getAnzahl(),
            'stp_freiplatz' => $this->asCharBool((int) $stellplatzFreiplatz?->getAnzahl() > 0),
            'stp_carport' => $this->asCharBool((int) $stellplatzCarport?->getAnzahl() > 0),
            'stp_garage' => $this->asCharBool((int) $stellplatzGarage?->getAnzahl() > 0),

            // Etagen
            'etage' => (string) $geo?->getEtage(),
            'anzahl_etagen' => (string) $geo?->getAnzahlEtagen(),

            // Ausstattung (boolean)
            'kamin' => $this->asCharBool($ausstattung?->getKamin()),
            'gartennutzung' => $this->asCharBool($ausstattung?->getGartennutzung()),
            'wg_geeignet' => $this->asCharBool($ausstattung?->getWgGeeignet()),
            'raeume_veraenderbar' => $this->asCharBool($ausstattung?->getRaeumeVeraenderbar()),
            'rollstuhlgerecht' => $this->asCharBool($ausstattung?->getRollstuhlgerecht()),
            'klimatisiert' => $this->asCharBool($ausstattung?->getKlimatisiert()),
            'wintergarten' => $this->asCharBool($ausstattung?->getWintergarten()),
            'sauna' => $this->asCharBool($ausstattung?->getSauna()),
            'badewanne' => $this->asCharBool($bad?->getWanne()),
            'dusche' => $this->asCharBool($bad?->getDusche()),
            'objausstattung__unterkellert' => $this->asCharBool(
                Unterkellert::KELLER_NEIN !== $ausstattung?->getUnterkellert()?->getKeller()
            ),

            // Ausstattung (serialized flags)
            'kueche' => $this->serializeFlags([
                'OFFEN' => $kueche?->getOffen() ?? false,
                'EBK' => $kueche?->getEbk() ?? false,
                'PANTRY' => $kueche?->getPantry() ?? false,
            ]),
            'boden' => $this->serializeFlags([
                'Dielen' => $boden?->getDielen() ?? false,
                'Doppelboden' => $boden?->getDoppelboden() ?? false,
                'Estrich' => $boden?->getEstrich() ?? false,
                'Fertigparkett' => $boden?->getFertigparkett() ?? false,
                'Fliesen' => $boden?->getFliesen() ?? false,
                'Granit' => $boden?->getGranit() ?? false,
                'Kunststoff' => $boden?->getKunststoff() ?? false,
                'Laminat' => $boden?->getLaminat() ?? false,
                'Linoleum' => $boden?->getLinoleum() ?? false,
                'Marmor' => $boden?->getMarmor() ?? false,
                'Parkett' => $boden?->getParkett() ?? false,
                'Stein' => $boden?->getStein() ?? false,
                'Teppich' => $boden?->getTeppich() ?? false,
                'Terrakotta' => $boden?->getTerrakotta() ?? false,
            ]),
            'heizungsart' => $this->serializeFlags([
                'Etage' => $heizungsart?->getEtage() ?? false,
                'Fern' => $heizungsart?->getFern() ?? false,
                'Fussboden' => $heizungsart?->getFussboden() ?? false,
                'Ofen' => $heizungsart?->getOfen() ?? false,
                'Zentral' => $heizungsart?->getZentral() ?? false,
            ]),
            'fahrstuhl' => $this->serializeFlags([
                'lasten' => $fahrstuhl?->getLasten() ?? false,
                'personen' => $fahrstuhl?->getPersonen() ?? false,
            ]),
            'ausricht_balkon_terrasse' => $this->serializeFlags([
                'nord' => $ausrichtungBalkonTerrasse?->getNord() ?? false,
                'ost' => $ausrichtungBalkonTerrasse?->getOst() ?? false,
                'sued' => $ausrichtungBalkonTerrasse?->getSued() ?? false,
                'west' => $ausrichtungBalkonTerrasse?->getWest() ?? false,
                'nordost' => $ausrichtungBalkonTerrasse?->getNordost() ?? false,
                'nordwest' => $ausrichtungBalkonTerrasse?->getNordwest() ?? false,
                'suedost' => $ausrichtungBalkonTerrasse?->getSuedost() ?? false,
                'suedwest' => $ausrichtungBalkonTerrasse?->getSuedwest() ?? false,
            ]),
            'stellplatzart' => $this->serializeFlags([
                'Garage' => $stellplatzArt?->getGarage() ?? false,
                'Tiefgarage' => $stellplatzArt?->getTiefgarage() ?? false,
                'Carport' => $stellplatzArt?->getCarport() ?? false,
                'Freiplatz' => $stellplatzArt?->getFreiplatz() ?? false,
                'Parkhaus' => $stellplatzArt?->getParkhaus() ?? false,
                'Duplex' => $stellplatzArt?->getDuplex() ?? false,
            ]),

            // Ausstattung (other)
            'moebliert' => (string) $ausstattung?->getMoebliert()?->getMoeb(),
            'anzahl_stellplaetze' => (string) $flaechen?->getAnzahlStellplaetze(),
            'ausstatt_kategorie' => (string) $ausstattung?->getAusstattKategorie(),

            // Distanzen
            'distanzen_kindergarten' => (string) ($distanzenMap['KINDERGAERTEN'] ?? ''),
            'distanzen_grundschule' => (string) ($distanzenMap['GRUNDSCHULE'] ?? ''),
            'distanzen_realschule' => (string) ($distanzenMap['REALSCHULE'] ?? ''),
            'distanzen_gymnasium' => (string) ($distanzenMap['GYMNASIUM'] ?? ''),
            'distanzen_autobahn' => (string) ($distanzenMap['AUTOBAHN'] ?? ''),
            'distanzen_bus' => (string) ($distanzenMap['BUS'] ?? ''),
            'distanzen_einkaufsmöglichkeiten' => (string) ($distanzenMap['EINKAUFSMOEGLICHKEITEN'] ?? ''),
            'distanzen_fernbahnhof' => (string) ($distanzenMap['FERNBAHNHOF'] ?? ''),
            'distanzen_flughafen' => (string) ($distanzenMap['FLUGHAFEN'] ?? ''),
            'distanzen_ubahn' => (string) ($distanzenMap['US_BAHN'] ?? ''),
            'distanzen_zentrum' => (string) ($distanzenMap['ZENTRUM'] ?? ''),

            // Energieausweis
            'energieausweis' => $this->asCharBool(null !== $energieAusweis),
            'energieausweis_typ' => (string) $energieAusweis?->getEpart(),
            'gueltig_bis' => $this->formatDate($energieAusweis?->getGueltigBis()),
            'energieverbrauchkennwert' => (string) $energieAusweis?->getEnergieverbrauchkennwert(),
            'energieeffizienzklasse' => (string) $energieAusweis?->getWertklasse(),
            'mitwarmwasser' => $this->asCharBool($energieAusweis?->getMitwarmwasser()),
            'endenergiebedarf' => (string) $energieAusweis?->getEndenergiebedarf(),
            'primaerenergietraeger' => (string) $energieAusweis?->getPrimaerenergietraeger(),
            'stromwert' => (string) $energieAusweis?->getStromwert(),
            'waermewert' => (string) $energieAusweis?->getWaermewert(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function compileAgentData(Immobilie $immobilie): array
    {
        $kontaktPerson = $immobilie->getKontaktperson();

        return [
            'external_id' => (string) $kontaktPerson?->getPersonennummer(),
            'anrede' => (string) $kontaktPerson?->getAnrede(),
            'firstname' => (string) $kontaktPerson?->getVorname(),
            'lastname' => (string) $kontaktPerson?->getName(),
            'email_direkt' => (string) $kontaktPerson?->getEmailDirekt(),
            'tel_durchwahl' => (string) $kontaktPerson?->getTelDurchw(),
        ];
    }

    /**
     * @return array<string, ResourceType>
     */
    private function compileResourceData(Immobilie $immobilie): array
    {
        $resources = [];

        foreach ($immobilie->getAnhaenge()?->getAnhang() ?? [] as $anhang) {
            if (null === ($path = $anhang->getDaten()?->getPfad())) {
                continue;
            }

            $resources[$path] = match ($anhang->getGruppe()) {
                Anhang::GRUPPE_TITELBILD => ResourceType::titleImage,
                Anhang::GRUPPE_BILD => ResourceType::galleryImage,
                Anhang::GRUPPE_DOKUMENTE => ResourceType::document,
                default => ResourceType::other,
            };
        }

        return $resources;
    }

    private function asCharBool(bool|null $state): string
    {
        return $state ? '1' : '';
    }

    /**
     * @param array<string, int|string|bool|null> $data
     */
    private function serializeFlags(array $data): string|null
    {
        $data = array_keys(array_filter($data));

        return empty($data) ? null : serialize($data);
    }

    private function formatMoney(float|null $moneyValueInInsaneFormat): string
    {
        return number_format($moneyValueInInsaneFormat ?? 0.0, 2, '.', '');
    }

    private function formatDate(string|null $date): string
    {
        if (null === $date) {
            return '';
        }

        try {
            return (new \DateTime($date))->format('d.m.Y');
        } catch (\Exception) {
            return '';
        }
    }
}
