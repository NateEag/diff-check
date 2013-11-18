<?php

// This function def. will cause an error in style checking. If we get one,
// then we know that processing a nested file didn't crash.
function foo()
{
    echo "Do something.\n"; die;
}
