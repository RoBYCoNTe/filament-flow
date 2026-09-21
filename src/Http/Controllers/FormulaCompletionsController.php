<?php

namespace RoBYCoNTe\FilamentFlow\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RoBYCoNTe\FilamentFlow\Support\CompletionPayload;
use RoBYCoNTe\FilamentFlow\Support\FormulaCompletionRegistry;

class FormulaCompletionsController
{
    public function __invoke(Request $request, FormulaCompletionRegistry $registry): JsonResponse
    {
        $scope = $request->string('scope')->toString();
        $contextType = $request->string('context_type')->toString();
        $contextId = $request->input('context_id');

        if (! $registry->has($scope)) {
            return response()->json(['error' => "Unknown scope '{$scope}'."], 404);
        }

        $provider = $registry->resolve($scope);

        $context = null;
        if ($contextType && $contextId && class_exists($contextType)) {
            $context = app($contextType)->find($contextId);
        }

        try {
            $payload = $provider->getCompletions($context);
        } catch (InvalidArgumentException $e) {
            $payload = CompletionPayload::empty();
        }

        return response()->json($payload->toArray());
    }
}
