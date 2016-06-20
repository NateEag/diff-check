About
=====

This git pre-commit hook blocks commits that contain style errors.

It only complains about errors added by the diff - if someone else committed
crap code in the past, it does not penalize you for leaving it alone. This
makes it useful for enforcing a style guideline on code that did not
previously have one (which is why it was written).

It is language- and style-agnostic. Its config file requires a command to
run the style check, so you can use whatever style checker and configuration
is best for your project.

This is still under development, and the interface may change, so it is not yet
documented. Read The Source if you would use it.

I'd like to adapt it to verify diffs and support multiple ways to generate
them, so that it isn't useful only as a git pre-commit hook. Baby steps.
