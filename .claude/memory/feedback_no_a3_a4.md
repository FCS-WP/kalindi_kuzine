---
name: Skip A3/A4 agent steps
description: User wants to skip A3 QA review and A4 report steps to save tokens — just build and report concisely
type: feedback
---

Skip A3 (QA cross-check agent) and A4 (report agent) steps when executing tasks. Just do the work (A2 backend + A1 frontend), run the build, and report concisely.

**Why:** Token optimization — the QA sub-agent exploration and verbose A4 reports consume too many tokens without enough value for the user.

**How to apply:** After writing code and running `npm run build`, just confirm success briefly. No need to spawn an Explore agent for QA cross-check or write a detailed layout diagram report.
