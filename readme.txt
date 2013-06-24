PHP Codesniffer is a nifty little thing, and an excellent way to catch coding
style violations.

Running it as a pre-commit hook is therefore an obvious thing to do.

However, when you're working on large legacy codebases, with files thousands of lines long and hardcoded values you don't want to touch as part of your current changes, applying style fixes to each file you've touched can actually be a significant source of pain.

Therefore, this little project has come to be.

The goal: run PHP CS on the files being comitted, but only complain if errors are detected on lines the current commit adds.

