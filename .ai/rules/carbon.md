---
paths:
  - 'app/Models/Land.php;app/Services/Land/**;app/Services/Carbon/**'
---

# Carbon

## Use completed analyses as current evidence
Pending and failed photo-analysis jobs are operational events, not current land evidence. latestAnalysis and derived dashboard/carbon calculations must select the newest completed analysis, using captured_at and id as deterministic tie-breakers, so a newer failed upload cannot erase the last reliable metrics.
