<?php

/**
 * CRUD operations for Testing with status and replace other events on the same dates
 *
 * Class TestingRepository
 * @package ZihiPro\Repositories
 */
class TestingRepository implements ITestingRepository
{

    public function createByLayout(array $data, string $layout)
    {
        DB::beginTransaction();
        try {
            /*check existing other events*/
            App::make('EventService')
                ->processExistingEvents(
                    EventService::TESTING,
                    $layout,
                    array_get($data, 'calendar'),
                    array_get($data, 'action')
                );

            /*check existing records inside event*/
            Testing::checkAndReplaceExistingEvents($data, $layout);

            switch ($layout) {
                case config('constants.layouts.available'):
                case config('constants.layouts.planned'): {
                    Testing::createBySlot(array_get($data, 'calendar'));
                    break;
                }
                case config('constants.layouts.done'): {
                    Testing::createDone(array_get($data, 'calendar'));
                    break;
                }
                default:
                    break;
            }
            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    public function updateByLayout(array $data, Testing $model)
    {
        DB::beginTransaction();
        try {

            /*check existing other events*/
            App::make('EventService')
                ->processExistingEvents(
                    EventService::TESTING,
                    $model->layout,
                    array_get($data, 'calendar'),
                    array_get($data, 'action')
                );

            /*check existing records inside event*/
            $exists = Testing::getExistingEvents(array_get($data, 'calendar'), $model->layout);
            $existsIds = array_pluck($exists, 'id');
            if (array_search($model->id, $existsIds) !== false) {
                unset($existsIds[array_search($model->id, $existsIds)]);
            }

            if ($existsIds && !empty($existsIds)) {
                if (array_has($data, 'action') && $data['action'] === EventService::REPLACE) {
                    Testing::activeAthlete()->visible()->whereIn('id', $existsIds)->{$model->layout}()->delete();
                } else {
                    throw new \Exception('On this date(s) testing already exist');
                }
            }

            switch ($model->layout) {
                case config('constants.layouts.available'):
                case config('constants.layouts.planned'): {
                    $update = array_first(array_get($data, 'calendar'));
                    if (array_has($update, 'dates')) {
                        $update['date'] = array_first(array_get($update, 'dates'));
                    }
                    $model->update($update);
                    break;
                }
                case config('constants.layouts.done'): {
                    Testing::updateDone($model, array_get($data, 'calendar'));
                    break;
                }
                default:
                    break;
            }

            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }
}

