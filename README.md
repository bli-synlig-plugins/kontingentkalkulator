# Kontingentkalkulator

WordPress-plugin med Flatsome UX Builder-elementer som regner ut reell fagforeningskontingent ut fra månedslønn eller timelønn, inkludert skattefradrag og valgfrie forsikringer.

**Versjon:** 1.0.0  
**Forfatter:** Benjamin Pedersen

## Krav

- WordPress 6.6 eller nyere
- PHP 8.1 eller nyere
- Flatsome (UX Builder-elementene)

## Installasjon

1. Last ned release-zip fra [Releases](https://github.com/bli-synlig-plugins/kontingentkalkulator/releases), eller klon dette repoet.
2. Legg mappen `kontingentkalkulator` i `wp-content/plugins/`.
3. Aktiver **Kontingentkalkulator** under **Plugins**.

## Admin

Gå til **Kontingentkalkulator** i wp-admin. Satser, tekster og forsikringer gjelder alle kalkulatorer. Farger og standardverdier settes på elementet i UX Builder.

- **Satser:** kontingentsats, skattefradragssats og maks fradragsgrunnlag per år.
- **Forsikringer:** legg til, rediger, vis/skjul og slett. Aktive forsikringer trekkes fra reell månedlig kostnad og vises som én rad i resultatet. De gir ikke skattefradrag.
- **Tekster:** overskrifter, feltetiketter og suffikser, gruppert som på nettsiden.
- **Forklaringer:** tekst bak (i)-knappene. `{sats}`, `{fradrag}` og `{arsgrense}` erstattes med verdiene under Satser.

Forhåndsvisningen til høyre oppdateres mens du skriver.

## UX Builder

Elementene ligger under kategorien **Kontingentkalkulator**:

| Element | Beskrivelse |
|---------|-------------|
| **Kontingentkalkulator** | Container. Legg inn input og resultat som barn. |
| **Kalkulator: input** | Lønn (måned eller time) og timer per uke. |
| **Kalkulator: resultat** | Reell kostnad, rader og forklaringer. |

Bruk forhåndsvalget **Standard** for å sette inn alle tre.

## Lisens

GPLv2 or later.
