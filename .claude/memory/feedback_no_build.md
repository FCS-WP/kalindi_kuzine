---
name: Don't run npm run build
description: User runs npm run dev in their terminal — skip running build after code changes
type: feedback
---

Don't run `npm run build` after making code changes. The user has `npm run dev` running in their terminal which watches and rebuilds automatically.

**Why:** Saves time and avoids duplicate builds. The dev watcher handles it.

**How to apply:** After editing JS/SCSS/JSX files, just confirm the change. No need to run build commands.
