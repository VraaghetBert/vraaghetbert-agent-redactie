# Agent-redactie

WordPress-plugin waarmee een AI-contentagent berichten en media klaarzet als **Ter beoordeling**. Publiceren en inplannen blijft bij de beheerder.

- Rol **Content-agent**: berichten schrijven, media uploaden, bestaande categorieën kiezen. Niet publiceren, niet verwijderen, geen pagina's, thema, plugins of gebruikers.
- **Vangnet** op het niveau van `wp_insert_post`: wat de agent ook probeert, het bericht wordt *Ter beoordeling*.
- **Melding** per bericht of één dagelijkse bundel (Instellingen → Agent-redactie).
- **Updates via GitHub**: verhoog `Version:` in `agent-redactie.php`, zet een kopje in `CHANGELOG.md` en push naar `main`. De workflow maakt een release met een zip die net zo heet als de repository (bijvoorbeeld `vraaghetbert-agent-redactie.zip`).

Werkt samen met een MCP-plugin die OAuth ondersteunt, zodat Claude de site als connector kan gebruiken.

Dezelfde code draait in twee repositories: `vraaghetbert-agent-redactie` (vraaghetbert.nl) en `plancijfer-agent-redactie` (plancijfer.nl). De mapnaam bepaalt vanzelf uit welke repository de updates komen.
